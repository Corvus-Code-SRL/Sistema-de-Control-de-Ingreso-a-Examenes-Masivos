import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import {
  Plus,
  Calendar,
  Search,
  MoreVertical,
  CalendarPlus,
  AlertCircle
} from 'lucide-react';

interface ExamItem {
  id: number;
  nombre: string;
  materia: string;
  grupo: string;
  fecha: string;
  horario: string;
  duracion: string;
  aula: string;
  postulantes: number;
  auxiliares: { initials: string; active?: boolean }[];
  auxiliaresText: string;
  estado: 'PROGRAMADO' | 'EN PREPARACIÓN' | 'BORRADOR';
}

export const ExamsPage: React.FC = () => {
  const navigate = useNavigate();
  const [exams] = useState<ExamItem[]>([]);
  const [searchTerm, setSearchTerm] = useState('');

  const renderStatusBadge = (estado: ExamItem['estado']) => {
    switch (estado) {
      case 'PROGRAMADO':
        return (
          <span className="px-3 py-1 rounded-full text-[11px] font-bold bg-[#DFF1E7] text-[#15803D]">
            PROGRAMADO
          </span>
        );
      case 'EN PREPARACIÓN':
        return (
          <span className="px-3 py-1 rounded-full text-[11px] font-bold bg-[#D8ECEE] text-[#005E68]">
            EN PREPARACIÓN
          </span>
        );
      case 'BORRADOR':
        return (
          <span className="px-3 py-1 rounded-full text-[11px] font-bold bg-[#FFF3C7] text-[#9A6F00]">
            BORRADOR
          </span>
        );
    }
  };

  const filteredExams = exams.filter((exam) => {
    if (!searchTerm.trim()) return true;
    const term = searchTerm.toLowerCase();
    return (
      exam.nombre.toLowerCase().includes(term) ||
      exam.materia.toLowerCase().includes(term) ||
      exam.aula.toLowerCase().includes(term)
    );
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-[#2C2C2C]">Exámenes programados</h1>
          <p className="text-sm text-[#6C757D] mt-0.5">
            Planifica y supervisa las fechas de ingreso masivo a evaluaciones
          </p>
        </div>
        <Button
          onClick={() => navigate('/exams/new')}
          className="bg-[#005E68] hover:bg-[#00555E] text-white font-semibold text-xs px-4 py-2.5 rounded-lg gap-2 shadow-xs"
        >
          <Plus className="h-4 w-4" /> Nuevo examen
        </Button>
      </div>

      {exams.length === 0 ? (
        <div className="bg-white rounded-xl border border-[#DDDDDD] p-16 text-center shadow-xs space-y-5 max-w-3xl mx-auto my-6">
          <div className="h-16 w-16 bg-[#D8ECEE] rounded-2xl flex items-center justify-center mx-auto text-[#005E68]">
            <CalendarPlus className="h-8 w-8" />
          </div>

          <div className="space-y-2 max-w-lg mx-auto">
            <h2 className="text-lg font-bold text-[#2C2C2C]">No tienes exámenes programados</h2>
            <p className="text-xs text-[#6C757D] leading-relaxed">
              Comienza planificando una fecha de evaluación para tu asignatura. Podrás habilitar el control de ingreso por QR/Cédula, asignar aulas con aforo controlado y sincronizar auxiliares de apoyo.
            </p>
          </div>

          <Button
            onClick={() => navigate('/exams/new')}
            className="bg-[#005E68] hover:bg-[#00555E] text-white font-semibold text-xs px-5 py-2.5 rounded-lg gap-2 mt-2"
          >
            <Plus className="h-4 w-4" /> Programar mi primer examen
          </Button>
        </div>
      ) : (
        <div className="space-y-4">
          <div className="bg-white rounded-xl border border-[#DDDDDD] p-3 flex flex-wrap items-center justify-between gap-3 shadow-xs">
            <div className="flex items-center gap-3 flex-1 min-w-[280px]">
              <div className="relative flex-1">
                <Search className="h-4 w-4 text-[#6C757D] absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  placeholder="Buscar por materia, examen o aula..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-9 pr-3 py-1.5 bg-[#F9FBFB] border border-[#DDDDDD] rounded-lg text-xs outline-none focus:border-[#005E68] focus:bg-white transition-colors"
                />
              </div>

              <select className="bg-[#F9FBFB] border border-[#DDDDDD] rounded-lg text-xs px-3 py-1.5 text-[#2C2C2C] outline-none font-medium">
                <option>Todas las Materias</option>
              </select>

              <select className="bg-[#F9FBFB] border border-[#DDDDDD] rounded-lg text-xs px-3 py-1.5 text-[#2C2C2C] outline-none font-medium">
                <option>Todos los Estados</option>
              </select>
            </div>

            <span className="text-xs text-[#6C757D] font-medium pr-2">
              {filteredExams.length} exámenes encontrados
            </span>
          </div>

          <div className="bg-white rounded-xl border border-[#DDDDDD] overflow-hidden shadow-xs">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-[#DDDDDD] bg-[#FAFCFC] text-[#6C757D] font-bold uppercase tracking-wider text-[11px]">
                    <th className="py-3 px-5">EXAMEN / ASIGNATURA</th>
                    <th className="py-3 px-5">FECHA Y HORARIO</th>
                    <th className="py-3 px-5">AULAS Y CUPO</th>
                    <th className="py-3 px-5">AUXILIARES</th>
                    <th className="py-3 px-5">ESTADO</th>
                    <th className="py-3 px-5 text-right">ACCIONES</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#DDDDDD]">
                  {filteredExams.map((exam) => (
                    <tr key={exam.id} className="hover:bg-[#F9FBFB] transition-colors">
                      <td className="py-4 px-5">
                        <p className="font-bold text-xs text-[#2C2C2C]">{exam.nombre}</p>
                        <p className="text-[11px] text-[#005E68] font-semibold mt-0.5">
                          {exam.materia} • {exam.grupo}
                        </p>
                      </td>

                      <td className="py-4 px-5">
                        <p className="font-semibold text-xs text-[#2C2C2C] flex items-center gap-1.5">
                          <Calendar className="h-3.5 w-3.5 text-[#6C757D]" />
                          {exam.fecha}
                        </p>
                        <p className="text-[11px] text-[#6C757D] mt-0.5">
                          {exam.horario} ({exam.duracion})
                        </p>
                      </td>

                      <td className="py-4 px-5">
                        <span className="inline-block bg-[#EBF4F5] text-[#005E68] font-semibold px-2.5 py-0.5 rounded-md text-[11px] border border-[#D0E6E8]">
                          🏛 {exam.aula}
                        </span>
                        <p className="text-[11px] text-[#6C757D] mt-1">
                          {exam.postulantes} postulantes
                        </p>
                      </td>

                      <td className="py-4 px-5">
                        {exam.auxiliares.length > 0 ? (
                          <div className="flex items-center gap-2">
                            <div className="flex -space-x-1.5 overflow-hidden">
                              {exam.auxiliares.map((auxiliary, index) => (
                                <div
                                  key={index}
                                  className="inline-block h-6 w-6 rounded-full ring-2 ring-white bg-[#D8ECEE] text-[#005E68] font-bold text-[10px] flex items-center justify-center"
                                >
                                  {auxiliary.initials}
                                </div>
                              ))}
                            </div>
                            <span className="text-[11px] text-[#6C757D] font-medium">
                              {exam.auxiliaresText}
                            </span>
                          </div>
                        ) : (
                          <span className="text-[11px] text-[#9A6F00] font-semibold flex items-center gap-1">
                            <AlertCircle className="h-3.5 w-3.5" />
                            {exam.auxiliaresText}
                          </span>
                        )}
                      </td>

                      <td className="py-4 px-5">
                        {renderStatusBadge(exam.estado)}
                      </td>

                      <td className="py-4 px-5 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            onClick={() => navigate('/exams/new')}
                            className="px-3 py-1 bg-[#F3F8F8] border border-[#DDDDDD] hover:bg-white text-[#2C2C2C] font-semibold text-xs rounded-md transition-colors"
                          >
                            {exam.estado === 'BORRADOR' ? 'Completar' : 'Configurar'}
                          </button>
                          <button className="p-1 text-[#6C757D] hover:text-[#2C2C2C] rounded-md hover:bg-gray-100">
                            <MoreVertical className="h-4 w-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="px-5 py-3 border-t border-[#DDDDDD] bg-[#FAFCFC] flex items-center justify-between text-xs text-[#6C757D]">
              <span>Página 1 de 1</span>
              <div className="flex items-center gap-2">
                <button disabled className="px-3 py-1 border border-[#DDDDDD] rounded-md bg-white opacity-50 cursor-not-allowed font-medium">
                  Anterior
                </button>
                <button className="px-3 py-1 bg-[#005E68] text-white rounded-md font-bold">
                  1
                </button>
                <button disabled className="px-3 py-1 border border-[#DDDDDD] rounded-md bg-white opacity-50 cursor-not-allowed font-medium">
                  Siguiente
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
