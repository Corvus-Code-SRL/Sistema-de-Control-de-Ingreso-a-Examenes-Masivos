import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { MainLayout } from '@/components/layout/MainLayout';
import { ExamsPage, CreateExamPage } from '@/features/exams';

// Componentes de marcador de posición para otras secciones del menú
const PlaceholderPage: React.FC<{ title: string }> = ({ title }) => (
  <div className="p-8 text-center rounded-2xl border border-dashed border-border bg-card/40 space-y-2">
    <h2 className="font-bold text-xl text-foreground">{title}</h2>
    <p className="text-sm text-muted-foreground">Módulo en desarrollo para los siguientes Sprints.</p>
  </div>
);

function App() {
  return (
    <BrowserRouter>
      <MainLayout>
        <Routes>
          <Route path="/" element={<Navigate to="/exams" replace />} />
          <Route path="/exams" element={<ExamsPage />} />
          <Route path="/exams/new" element={<CreateExamPage />} />
          <Route path="/groups" element={<PlaceholderPage title="Grupos Académicos (HU-016, HU-017)" />} />
          <Route path="/subjects" element={<PlaceholderPage title="Gestión de Materias" />} />
          <Route path="/students" element={<PlaceholderPage title="Nómina de Estudiantes (HU-021)" />} />
          <Route path="/classrooms" element={<PlaceholderPage title="Ambientes y Aulas" />} />
          <Route path="/entry-control" element={<PlaceholderPage title="Control de Ingreso" />} />
          <Route path="/incidents" element={<PlaceholderPage title="Registro de Incidentes" />} />
          <Route path="*" element={<Navigate to="/exams" replace />} />
        </Routes>
      </MainLayout>
    </BrowserRouter>
  );
}

export default App;
