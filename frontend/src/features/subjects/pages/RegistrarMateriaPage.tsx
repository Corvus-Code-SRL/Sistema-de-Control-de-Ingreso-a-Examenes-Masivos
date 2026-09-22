import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { MateriaFormState, FormErrors, CarreraSeleccionada } from '../types/subject.types';
import { registrarMateria } from '../services/subjectsService';

export const RegistrarMateriaPage: React.FC = () => {
  const navigate = useNavigate();

  const [formData, setFormData] = useState<MateriaFormState>({
    codigo: '',
    nombre: '',
    descripcion: '',
    carreras: []
  });

  const [errors, setErrors] = useState<FormErrors>({});
  const [warnings, setWarnings] = useState<{ global?: string; nombre?: string }>({});

  const [showCancelModal, setShowCancelModal] = useState(false);
  const [showSuccessToast, setShowSuccessToast] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Simulación del catálogo de carreras para el selector
  const carrerasDisponibles = [
    { id_carrera: 1, nombre: 'Ing. de Sistemas', activa: true },
    { id_carrera: 2, nombre: 'Ing. Informática', activa: true },
    { id_carrera: 3, nombre: 'Ing. Electrónica', activa: true },
    { id_carrera: 4, nombre: 'Ing. Civil', activa: true },
    { id_carrera: 5, nombre: 'Ing. Electromecánica', activa: false }
  ];

  const [selectedCarreraId, setSelectedCarreraId] = useState<number | ''>('');

  const hasUnsavedChanges = formData.codigo !== '' || formData.nombre !== '' || formData.carreras.length > 0;

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    if (errors[name as keyof FormErrors]) setErrors(prev => ({ ...prev, [name]: undefined, global: undefined }));
    if (warnings[name as keyof typeof warnings]) setWarnings({});
  };

  const handleAddCarrera = () => {
    if (!selectedCarreraId) return;
    const carrera = carrerasDisponibles.find(c => c.id_carrera === Number(selectedCarreraId));
    if (!carrera || !carrera.activa || formData.carreras.some(c => c.id_carrera === carrera.id_carrera)) return;

    const nuevaCarrera: CarreraSeleccionada = {
      id_carrera: carrera.id_carrera,
      nombre: carrera.nombre,
      nivel_semestre: 'Semestre 1',
      obligatoria: true
    };

    setFormData(prev => ({ ...prev, carreras: [...prev.carreras, nuevaCarrera] }));
    setSelectedCarreraId('');
    if (errors.carreras) setErrors(prev => ({ ...prev, carreras: undefined, global: undefined }));
  };

  const handleRemoveCarrera = (id_carrera: number) => {
    setFormData(prev => ({ ...prev, carreras: prev.carreras.filter(c => c.id_carrera !== id_carrera) }));
  };

  const handleCancelClick = () => {
    if (hasUnsavedChanges) setShowCancelModal(true);
    else navigate('/materias');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});

    // Validación Frontend básica
    const newErrors: FormErrors = {};
    const codigo = formData.codigo.trim();

    if (!codigo) {
      newErrors.codigo = 'Ingrese el código de la materia.';
    } else if (!/^\d{7}$/.test(codigo)) {
      newErrors.codigo = 'El código debe contener exactamente 7 dígitos.';
    }
    if (!formData.nombre.trim()) newErrors.nombre = 'Ingrese el nombre de la materia.';
    if (formData.carreras.length === 0) newErrors.carreras = 'Agregue al menos una carrera.';

    if (Object.keys(newErrors).length > 0) {
      newErrors.global = `Corrija ${Object.keys(newErrors).length} campos para registrar la materia.`;
      setErrors(newErrors);
      return;
    }

    // A4.6 - Aviso de nombre similar
    if (formData.nombre === 'Bases de Datos II' && !warnings.nombre) {
      setWarnings({
        global: 'Puede continuar, pero revise el aviso',
        nombre: 'Ya existe «Bases de Datos II» con el código 2008035. Revise que no sea la misma materia.'
      });
      return;
    }

    try {
      setIsSubmitting(true);
      await registrarMateria(formData);

      // A4.7 - Mostrar Toast de Éxito
      setShowSuccessToast(true);
      setTimeout(() => {
        navigate('/materias');
      }, 3000);

    } catch (error: any) {
      // Manejo de error 422 de Laravel
      if (error?.data?.errors || error?.errors) {
        const serverErrors = error.data?.errors || error.errors;
        setErrors({
          global: 'Existen errores de validación en el servidor.',
          codigo: serverErrors.codigo ? serverErrors.codigo[0] : undefined,
          nombre: serverErrors.nombre ? serverErrors.nombre[0] : undefined,
        });
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex flex-col flex-1 min-w-0 bg-background font-inter text-foreground relative">
      <header className="flex-none h-16 px-8 bg-surface border-b border-border flex items-center justify-between">
        <div className="flex items-center gap-1.5 text-[13px] text-muted">
          <span>Catálogo académico</span><span className="text-border-strong">›</span><span>Materias</span><span className="text-border-strong">›</span><b className="font-medium text-foreground">Registrar materia</b>
        </div>
        <div className="flex items-center gap-3">
          <span className="flex items-center h-8 px-3 rounded-full bg-primary-soft text-primary-deep text-[13px] font-semibold">Período 2-2026</span>
        </div>
      </header>

      <main className="flex-1 p-8 flex flex-col gap-6 max-w-[1168px] mx-auto w-full relative">

        {/* Toast de Éxito (A4.7) */}
        {showSuccessToast && (
          <div className="fixed top-20 right-8 w-[420px] z-50 flex gap-3 p-4 bg-surface border-l-4 border-l-ok border border-border rounded-lg shadow-lg">
            <svg className="flex-none text-ok mt-[1px]" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg>
            <div className="flex-1 min-w-0">
              <div className="font-semibold text-[14px]">Materia registrada</div>
              <div className="text-[13px] text-muted leading-tight mt-0.5">
                {formData.codigo} · {formData.nombre}. Ya aparece en el catálogo de los docentes de las carreras asignadas.
              </div>
            </div>
            <button onClick={() => setShowSuccessToast(false)} className="text-muted hover:text-foreground">
              <svg width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
          </div>
        )}

        <div className="flex items-end justify-between gap-6">
          <div className="flex flex-col gap-1.5">
            <button onClick={() => navigate('/materias')} className="inline-flex items-center gap-1.5 text-[13px] font-semibold text-primary mb-0.5 hover:text-primary-hover">
              <svg width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
              Materias
            </button>
            <h1 className="text-[28px] leading-9 font-semibold text-foreground tracking-tight">Registrar materia</h1>
            <p className="text-[14px] text-muted">Los campos con * son obligatorios.</p>
          </div>
        </div>

        <div className="flex items-start gap-5">
          <form onSubmit={handleSubmit} className="flex-1 flex flex-col gap-5">

            {/* Alerta Global de Error */}
            {errors.global && (
              <div className="flex gap-3 p-3 bg-danger-soft border border-danger/30 rounded-lg text-danger-fg text-[14px]">
                <svg className="flex-none mt-[1px]" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="M12 16h.01"/><path d="M12 8v4"/><path d="M15.312 2a2 2 0 0 1 1.414.586l4.688 4.688A2 2 0 0 1 22 8.688v6.624a2 2 0 0 1-.586 1.414l-4.688 4.688a2 2 0 0 1-1.414.586H8.688a2 2 0 0 1-1.414-.586l-4.688-4.688A2 2 0 0 1 2 15.312V8.688a2 2 0 0 1 .586-1.414l4.688-4.688A2 2 0 0 1 8.688 2z"/></svg>
                <div className="flex-1"><div className="font-semibold">{errors.global}</div></div>
              </div>
            )}

            {/* Alerta Global de Advertencia (A4.6) */}
            {warnings.global && !errors.global && (
              <div className="flex gap-3 p-3 bg-warn-soft border border-warn-border rounded-lg text-warn-fg text-[14px]">
                <svg className="flex-none mt-[1px]" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/></svg>
                <div className="flex-1"><div className="font-semibold">{warnings.global}</div></div>
              </div>
            )}

            <div className="bg-surface border border-border rounded-lg shadow-sm">
              <div className="p-4 border-b border-border flex flex-col gap-0.5">
                <span className="text-[16px] leading-[24px] font-semibold">Datos de la materia</span>
                <span className="text-[13px] text-muted">Se muestran a los docentes en el catálogo.</span>
              </div>
              <div className="p-5 flex flex-col gap-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="flex flex-col gap-1.5 min-w-0">
                    <label className="text-[14px] font-medium flex gap-1">Código <span className="text-danger">*</span></label>
                    <input
                      type="text"
                      name="codigo"
                      value={formData.codigo}
                      onChange={handleInputChange}
                      placeholder="Ej. 2008120"
                      inputMode="numeric"
                      maxLength={7}
                      className={`h-10 px-3 rounded-md border bg-surface text-foreground text-[14px] outline-none ${errors.codigo ? 'border-danger focus:ring-2 focus:ring-danger-soft' : 'border-border-strong focus:border-primary focus:ring-2 focus:ring-primary-soft'}`}
                    />
                    {errors.codigo ? (
                      <div className="flex gap-1.5 text-[13px] text-danger-fg font-medium"><span>{errors.codigo}</span></div>
                    ) : (
                      <div className="text-[13px] text-muted">Código institucional de 7 dígitos.</div>
                    )}
                  </div>
                  <div className="flex flex-col gap-1.5 min-w-0">
                    <label className="text-[14px] font-medium flex gap-1">Nombre <span className="text-danger">*</span></label>
                    <input
                      type="text"
                      name="nombre"
                      value={formData.nombre}
                      onChange={handleInputChange}
                      placeholder="Ej. Bases de Datos II"
                      className={`h-10 px-3 rounded-md border bg-surface text-foreground text-[14px] outline-none ${errors.nombre ? 'border-danger focus:ring-2 focus:ring-danger-soft' : warnings.nombre ? 'border-[#E8B720]' : 'border-border-strong focus:border-primary focus:ring-2 focus:ring-primary-soft'}`}
                    />
                    {errors.nombre ? (
                      <div className="flex gap-1.5 text-[13px] text-danger-fg font-medium"><span>{errors.nombre}</span></div>
                    ) : warnings.nombre ? (
                      <div className="flex gap-1.5 text-[13px] text-warn-fg font-medium">
                        <svg className="flex-none mt-[1px]" width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/></svg>
                        <span>{warnings.nombre}</span>
                      </div>
                    ) : (
                      <div className="text-[13px] text-muted">{formData.nombre.length} / 50 caracteres.</div>
                    )}
                  </div>
                </div>
                <div className="flex flex-col gap-1.5 min-w-0">
                  <label className="text-[14px] font-medium flex gap-1">Descripción <span className="text-muted text-[13px] font-normal">(opcional)</span></label>
                  <textarea
                    name="descripcion"
                    value={formData.descripcion}
                    onChange={handleInputChange}
                    placeholder="Contenido general de la materia"
                    className="min-h-[88px] p-3 rounded-md border border-border-strong bg-surface text-foreground text-[14px] outline-none resize-y focus:border-primary focus:ring-2 focus:ring-primary-soft"
                  />
                </div>
              </div>
            </div>

            <div className="bg-surface border border-border rounded-lg shadow-sm">
              <div className="p-4 border-b border-border flex items-center justify-between">
                <div className="flex flex-col gap-0.5">
                  <span className="text-[16px] leading-[24px] font-semibold flex items-center gap-1">Carreras donde se dicta <span className="text-danger">*</span></span>
                  <span className="text-[13px] text-muted">Para cada carrera, el nivel y si es obligatoria o electiva.</span>
                </div>
                <span className="h-5 px-2 inline-flex items-center rounded-full bg-border text-muted text-[12px] font-semibold">
                  {formData.carreras.length} carreras
                </span>
              </div>
              <div className="p-5 flex flex-col gap-3">
                <div className="flex items-end gap-2.5">
                  <div className="flex flex-col gap-1.5 w-56">
                    <label className="text-[14px] font-medium">Facultad</label>
                    <select disabled className="h-10 px-3 rounded-md border border-border bg-background text-muted text-[14px] outline-none appearance-none">
                      <option>Ciencias y Tecnología</option>
                    </select>
                  </div>
                  <div className="flex flex-col gap-1.5 flex-1">
                    <label className="text-[14px] font-medium">Carrera</label>
                    <select
                      value={selectedCarreraId}
                      onChange={(e) => setSelectedCarreraId(Number(e.target.value))}
                      className="h-10 px-3 rounded-md border border-border-strong bg-surface text-foreground text-[14px] outline-none focus:border-primary focus:ring-2 focus:ring-primary-soft"
                    >
                      <option value="" disabled>Elija una carrera activa</option>
                      {carrerasDisponibles.map(c => {
                        const isAdded = formData.carreras.some(sc => sc.id_carrera === c.id_carrera);
                        return (
                          <option key={c.id_carrera} value={c.id_carrera} disabled={!c.activa || isAdded}>
                            {c.nombre} {!c.activa ? '· inactiva' : isAdded ? '· ya agregada' : ''}
                          </option>
                        );
                      })}
                    </select>
                  </div>
                  <button type="button" onClick={handleAddCarrera} disabled={!selectedCarreraId} className="h-10 px-4 inline-flex items-center gap-2 bg-surface border border-border-strong text-foreground text-[14px] font-semibold rounded-lg hover:bg-background disabled:opacity-50">
                    <svg width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="M12 5v14"/></svg> Agregar carrera
                  </button>
                </div>

                {errors.carreras && (
                  <div className="flex gap-2 p-3 mt-1 bg-danger-soft border border-danger/30 rounded-lg text-danger-fg text-[14px]">
                    <span className="font-semibold">{errors.carreras}</span>
                  </div>
                )}

                <div className="flex flex-col gap-2 mt-2">
                  {formData.carreras.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 p-7 bg-sunken border-[1.5px] border-dashed border-border-strong rounded-xl text-center">
                      <svg className="text-primary" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>
                      <span className="text-[13px] text-muted">Todavía no agregó carreras. Una materia debe dictarse en al menos una.</span>
                    </div>
                  ) : (
                    formData.carreras.map(c => (
                      <div key={c.id_carrera} className="flex items-center gap-3 p-2.5 border border-border rounded-lg bg-surface shadow-sm">
                        <div className="flex-1 flex flex-col gap-0.5">
                          <span className="font-semibold text-[14px]">{c.nombre}</span>
                          <span className="text-[12px] text-muted">Sin grupos</span>
                        </div>
                        <div className="w-[150px]">
                          <select className="h-8 px-2 w-full border border-border-strong bg-surface text-foreground rounded text-[13px] outline-none">
                            <option>Semestre 1</option>
                            <option>Semestre 3</option>
                            <option>Semestre 5</option>
                          </select>
                        </div>
                        <div className="inline-flex p-[3px] bg-sunken rounded-[10px] gap-0.5">
                          <span className="h-8 px-3 flex items-center rounded-lg bg-surface text-foreground font-semibold shadow-sm text-[13px]">Obligatoria</span>
                          <span className="h-8 px-3 flex items-center rounded-lg text-muted font-medium text-[13px]">Electiva</span>
                        </div>
                        <button type="button" onClick={() => handleRemoveCarrera(c.id_carrera)} className="w-8 h-8 flex items-center justify-center text-danger-fg hover:bg-danger-soft rounded-lg">
                          <svg width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                        </button>
                      </div>
                    ))
                  )}
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-1">
              <button type="button" onClick={handleCancelClick} className="h-10 px-4 font-semibold text-foreground bg-surface border border-border-strong rounded-lg hover:bg-background">
                Cancelar
              </button>
              <button type="submit" disabled={isSubmitting} className="h-10 px-4 inline-flex items-center gap-2 font-semibold text-white bg-primary rounded-lg hover:bg-primary-hover active:bg-primary-active disabled:opacity-70">
                <svg width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                {isSubmitting ? 'Registrando...' : 'Registrar materia'}
              </button>
            </div>
          </form>

          <aside className="w-[320px] flex-none">
            <div className="bg-surface border border-border rounded-lg p-5 flex flex-col gap-2.5 shadow-sm">
              <span className="text-[16px] font-semibold text-foreground">Cómo se usa</span>
              <span className="text-[13px] text-muted leading-relaxed">
                La materia aparece en el catálogo de los docentes de cada carrera, sin grupos. Los docentes la vinculan al registrar sus grupos.
              </span>
              <div className="h-[1px] bg-border w-full"></div>
              <span className="text-[13px] text-muted leading-relaxed">
                Se registra en estado <b className="text-foreground font-semibold">Activa</b>. Para ocultarla, desactívela después.
              </span>
            </div>
          </aside>
        </div>
      </main>

      {/* Modal de Confirmación de Cancelación (A4.7) */}
      {showCancelModal && (
        <div className="absolute inset-0 bg-[rgba(16,28,30,.55)] z-20 flex items-center justify-center p-8">
          <div className="bg-surface rounded-2xl shadow-lg flex flex-col overflow-hidden max-w-full w-[440px]">
            <div className="p-6 pb-1 flex items-start gap-3">
              <span className="w-10 h-10 flex-none rounded-full flex items-center justify-center bg-danger-soft text-danger">
                <svg width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
              </span>
              <div className="flex-1 min-w-0">
                <div className="text-[18px] leading-[26px] font-semibold">¿Descartar la materia?</div>
              </div>
            </div>
            <div className="flex-1 min-h-0 overflow-hidden px-6 pt-4 pb-5 flex flex-col gap-4">
              <p className="text-[13px] text-[#4F5B62] leading-[19px]">
                Se perderán el código, el nombre y las {formData.carreras.length} carreras agregadas. No se registra nada.
              </p>
            </div>
            <div className="px-6 py-3.5 border-t border-border flex justify-end gap-2 bg-[#FBFCFC]">
              <button onClick={() => setShowCancelModal(false)} className="h-10 px-4 font-semibold text-foreground bg-surface border border-border-strong rounded-lg hover:bg-background">Seguir editando</button>
              <button onClick={() => navigate('/materias')} className="h-10 px-4 font-semibold text-white bg-danger border border-transparent rounded-lg hover:bg-[#A21B12]">Descartar</button>
            </div>
          </div>
        </div>
      )}

    </div>
  );
};