import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Plus, CheckCircle2, Loader2 } from 'lucide-react';
import { ExamsFilterBar } from '../components/ExamsFilterBar';
import { ScheduledExamsList } from '../components/ScheduledExamsList';
import { ExamsEmptyState } from '../components/ExamsEmptyState';
import { ScheduledExamItem, DayGroupedExams, Subject, Exam } from '../types/exams.types';
import { examsService } from '../services/examsService';

function formatTime(timeStr?: string): string {
  if (!timeStr) return '08:00';
  return timeStr.slice(0, 5); // Convert "08:00:00" -> "08:00"
}

function mapExamToScheduledItem(exam: Exam): ScheduledExamItem {
  let dayNum = '01';
  let daySubtitle = 'Programado';

  if (exam.fecha) {
    const datePart = String(exam.fecha).split('T')[0];
    const parts = datePart.split('-');
    if (parts.length === 3) {
      const year = parseInt(parts[0], 10);
      const month = parseInt(parts[1], 10) - 1;
      const day = parseInt(parts[2], 10);
      const dateObj = new Date(year, month, day);

      dayNum = String(day).padStart(2, '0');
      const dayName = dateObj.toLocaleDateString('es-ES', { weekday: 'short' });
      const monthName = dateObj.toLocaleDateString('es-ES', { month: 'short' });

      const today = new Date();
      const isToday =
        today.getFullYear() === year &&
        today.getMonth() === month &&
        today.getDate() === day;

      daySubtitle = isToday ? `Hoy · ${dayName}` : `${dayName} · ${monthName}`;
    }
  }

  const materiaNombre = exam.materia?.nombre || 'Sin materia';

  const gruposStr =
    exam.grupos && exam.grupos.length > 0
      ? `Grupo ${exam.grupos.map((g) => g.num_grupo).join(', ')}`
      : 'Sin grupo';

  const aulasStr =
    exam.ambientes && exam.ambientes.length > 0
      ? exam.ambientes.map((a) => a.nro_aula).join(', ')
      : 'Sin aula';

  const totalEstudiantes = exam.grupos
    ? exam.grupos.reduce((acc, g) => acc + (g.cantidad_estudiantes ?? g.inscritos_count ?? 0), 0)
    : 0;

  const startFmt = formatTime(exam.hora_inicio);
  const endFmt = formatTime(exam.hora_fin);

  return {
    id: exam.id_examen,
    nombre: exam.nombre_examen,
    materia: materiaNombre,
    id_materia: exam.materia?.id_materia,
    grupoStr: gruposStr,
    fechaISO: String(exam.fecha || ''),
    dayNum,
    daySubtitle,
    horario: `${startFmt} a ${endFmt}`,
    estadoBadge: {
      label: 'En configuración',
      type: 'info',
    },
    aulas: aulasStr,
    habilitadosCount: totalEstudiantes,
    auxiliaresStr: 'Sin auxiliares',
    canEdit: true,
  };
}

export const ExamsPage: React.FC = () => {
  const navigate = useNavigate();
  const location = useLocation();

  const [exams, setExams] = useState<ScheduledExamItem[]>([]);
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedSubjectId, setSelectedSubjectId] = useState('');
  const [notification, setNotification] = useState<string | null>(null);

  // Check if redirected with notification message (e.g. after creating an exam)
  useEffect(() => {
    if (location.state && (location.state as { message?: string }).message) {
      setNotification((location.state as { message: string }).message);
      window.history.replaceState({}, document.title);
    }
  }, [location]);

  // Load subject list and scheduled exams from backend API
  useEffect(() => {
    let isMounted = true;
    async function loadData() {
      try {
        setLoading(true);
        const [formDataRes, rawExams] = await Promise.all([
          examsService.getFormData().catch(() => ({ materias: [], ambientes: [], grupos: [] })),
          examsService.getAllExams().catch(() => []),
        ]);

        if (isMounted) {
          if (formDataRes && formDataRes.materias) {
            setSubjects(formDataRes.materias);
          }

          const examArray = Array.isArray(rawExams)
            ? rawExams
            : (rawExams as { data?: Exam[] })?.data && Array.isArray((rawExams as { data?: Exam[] }).data)
              ? (rawExams as { data: Exam[] }).data
              : [];

          const mapped = examArray.map(mapExamToScheduledItem);
          setExams(mapped);
        }
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    }
    loadData();
    return () => {
      isMounted = false;
    };
  }, []);

  // Filter exams by search term and selected subject
  const filteredExams = exams.filter((exam) => {
    const matchesSearch =
      !searchTerm.trim() ||
      exam.nombre.toLowerCase().includes(searchTerm.toLowerCase()) ||
      exam.materia.toLowerCase().includes(searchTerm.toLowerCase()) ||
      exam.aulas.toLowerCase().includes(searchTerm.toLowerCase());

    const matchesSubject =
      !selectedSubjectId || (exam.id_materia && String(exam.id_materia) === selectedSubjectId);

    return matchesSearch && matchesSubject;
  });

  // Group filtered exams by day for the agenda layout
  const dayGroups: DayGroupedExams[] = [];
  filteredExams.forEach((exam) => {
    let group = dayGroups.find(
      (g) => g.dayNum === exam.dayNum && g.daySubtitle === exam.daySubtitle
    );
    if (!group) {
      group = {
        dayNum: exam.dayNum,
        daySubtitle: exam.daySubtitle,
        exams: [],
      };
      dayGroups.push(group);
    }
    group.exams.push(exam);
  });

  const handleEditExam = (exam: ScheduledExamItem) => {
    navigate('/exams/new', { state: { examId: exam.id, editMode: true } });
  };

  const handleCancelExam = (exam: ScheduledExamItem) => {
    if (window.confirm(`¿Está seguro de cancelar el examen "${exam.nombre}"?`)) {
      setExams((prev) => prev.filter((e) => e.id !== exam.id));
    }
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      {/* Top Banner Notification if created successfully */}
      {notification && (
        <div className="bg-[#DFF1E7] border border-[#B6DEC6] text-[#15803D] px-4 py-3 rounded-xl flex items-center justify-between text-xs font-semibold shadow-2xs animate-in fade-in duration-200">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="h-4 w-4 shrink-0" />
            <span>{notification}</span>
          </div>
          <button
            onClick={() => setNotification(null)}
            className="hover:opacity-75 text-[#15803D] font-bold px-1"
          >
            ×
          </button>
        </div>
      )}

      {/* Main Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#E2E8F0] pb-5">
        <div className="space-y-1">
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold tracking-tight text-[#1F2937]">
              Programados
            </h1>
            <span className="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#E2E8F0] text-[#475569] hidden sm:inline-block">
              Periodo 2-2026
            </span>
          </div>
          <p className="text-xs text-[#5B6770] font-medium leading-relaxed">
            Exámenes en configuración. Se pueden editar hasta que abre su control de ingreso.
          </p>
        </div>

        <Button
          onClick={() => navigate('/exams/new')}
          className="bg-[#005E68] hover:bg-[#004D56] text-white font-semibold text-xs h-10 px-4 rounded-xl gap-2 shadow-xs transition-colors shrink-0 self-start sm:self-auto"
        >
          <Plus className="h-4 w-4" /> Nuevo examen
        </Button>
      </div>

      {/* Filter and Search Bar */}
      {exams.length > 0 && (
        <ExamsFilterBar
          searchTerm={searchTerm}
          onSearchChange={setSearchTerm}
          selectedSubjectId={selectedSubjectId}
          onSubjectChange={setSelectedSubjectId}
          subjects={subjects}
        />
      )}

      {/* Main Content Area */}
      {loading ? (
        <div className="flex flex-col items-center justify-center min-h-[300px] gap-3 text-[#5B6770]">
          <Loader2 className="h-7 w-7 animate-spin text-[#005E68]" />
          <p className="text-xs font-medium">Cargando exámenes programados...</p>
        </div>
      ) : exams.length === 0 ? (
        <ExamsEmptyState onNewExamClick={() => navigate('/exams/new')} />
      ) : dayGroups.length === 0 ? (
        <ExamsEmptyState
          onNewExamClick={() => navigate('/exams/new')}
          isFiltered={true}
        />
      ) : (
        <ScheduledExamsList
          dayGroups={dayGroups}
          onEditExam={handleEditExam}
          onCancelExam={handleCancelExam}
        />
      )}
    </div>
  );
};
