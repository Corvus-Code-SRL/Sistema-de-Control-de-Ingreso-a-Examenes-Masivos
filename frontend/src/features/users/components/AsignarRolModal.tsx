import React, { useState, useEffect } from 'react';
import { X, GraduationCap, User, Building, Layers, Lock, CheckCircle2, Loader2, AlertTriangle, ArrowRightLeft, Info } from 'lucide-react';

interface AsignarRolModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  usuario: {
    id_usuario: string;
    nombre: string;
    paterno: string;
    materno: string;
    cod_sis: string;
    tipo_institucional: string;
    rol_actual?: number | null;
    estado?: string;
  } | null;
}

// Configuración visual para mapear la data de la Base de Datos
const UI_CONFIG: Record<string, { icon: any, requiere: string }> = {
  'Docente': { icon: GraduationCap, requiere: 'Docente' },
  'Auxiliar': { icon: User, requiere: 'Estudiante' },
  'Administrador': { icon: Building, requiere: 'Funcionario' },
  'Supervisor de admisión': { icon: Layers, requiere: 'Docente' }
};

export const AsignarRolModal: React.FC<AsignarRolModalProps> = ({ isOpen, onClose, onSuccess, usuario }) => {
  const [step, setStep] = useState<'select' | 'confirm'>('select');
  const [rolesBd, setRolesBd] = useState<any[]>([]);
  const [isLoadingRoles, setIsLoadingRoles] = useState(false);
  const [selectedRol, setSelectedRol] = useState<number | null>(null);
  
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [impactData, setImpactData] = useState<any>(null);

  // CONEXIÓN BACKEND 1: Obtener el catálogo de roles
  useEffect(() => {
    if (isOpen) {
      cargarCatalogos();
      setStep('select');
      setSelectedRol(usuario?.rol_actual || null);
    }
  }, [isOpen, usuario]);

  const cargarCatalogos = async () => {
    setIsLoadingRoles(true);
    try {
      const response = await fetch('/api/roles', { headers: { 'Accept': 'application/json' }});
      if (response.ok) {
        const data = await response.json();
        setRolesBd(data.data || []);
      }
    } catch (error) {
      console.error("Error cargando roles del backend", error);
    } finally {
      setIsLoadingRoles(false);
    }
  };

  if (!isOpen || !usuario) return null;

  const nombreCompleto = `${usuario.nombre} ${usuario.paterno} ${usuario.materno || ''}`.trim();
  const iniciales = `${usuario.nombre.charAt(0)}${usuario.paterno.charAt(0)}`.toUpperCase();
  const isSinCambios = selectedRol === usuario.rol_actual;

  // CONEXIÓN BACKEND 2: Consultar impacto antes de cambiar
  const handleContinuar = async () => {
    if (!selectedRol) return;

    if (usuario.rol_actual && !isSinCambios) {
      setIsSubmitting(true);
      try {
        const res = await fetch(`/api/usuarios/${usuario.id_usuario}/asignaciones`, {
          headers: { 'Accept': 'application/json' }
        });
        if (res.ok) {
          const data = await res.json();
          setImpactData(data.data); // Expected: { grupos: X, tiene_activas: boolean }
        }
      } catch (error) {
        console.error("Error consultando impacto en el backend", error);
      } finally {
        setIsSubmitting(false);
        setStep('confirm'); // Pasa a pantalla A3.4
      }
    } else {
      handleSubmitFinal(); // Si no tiene rol actual, guarda directo
    }
  };

  // CONEXIÓN BACKEND 3: Guardar el rol en la base de datos
  const handleSubmitFinal = async () => {
    setIsSubmitting(true);
    setErrorMsg(null);

    try {
      const response = await fetch(`/api/usuarios/${usuario.id_usuario}/rol`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ id_rol: selectedRol })
      });

      if (response.ok) {
        onSuccess(); 
      } else {
        const data = await response.json();
        setErrorMsg(data.message || 'Error al asignar el rol en el servidor.');
        setStep('select');
      }
    } catch (error) {
      setErrorMsg('Error de conexión con el backend.');
      setStep('select');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleClose = () => {
    onClose();
  };

  const getRolName = (id: number | null | undefined) => {
    return rolesBd.find(r => r.id_rol === id)?.nombre_rol || 'Ninguno';
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-[#101C1E]/55 p-4 sm:p-6">
      
      {/* ---------------- PANTALLA A3.1 / A3.2 / A3.3 / A3.5 ---------------- */}
      {step === 'select' && (
        <div className="bg-white rounded-[20px] shadow-2xl w-full max-w-[600px] flex flex-col max-h-[90vh]">
          <div className="flex items-start justify-between p-6 pb-4">
            <div>
              <h2 className="text-[20px] font-bold text-[#2C2C2C]">
                {usuario.rol_actual ? 'Cambiar rol' : 'Asignar rol'}
              </h2>
              <p className="text-[13px] text-[#6C757D] mt-1">Una cuenta tiene un solo rol a la vez.</p>
            </div>
            <button onClick={handleClose} className="p-2 text-gray-400 hover:bg-gray-100 rounded-xl transition-colors">
              <X size={20} />
            </button>
          </div>

          <div className="flex-1 overflow-y-auto px-6 pb-6 space-y-5">
            {/* Header del Usuario */}
            <div className="flex items-center gap-4 p-4 bg-[#F7FAFA] border border-[#DDDDDD] rounded-2xl shadow-sm">
              <div className="w-12 h-12 rounded-full bg-[#D8ECEE] text-[#005E68] text-[16px] font-bold flex items-center justify-center flex-none">
                {iniciales}
              </div>
              <div className="flex-1 min-w-0">
                <div className="font-bold text-[16px] text-[#2C2C2C] truncate">{nombreCompleto}</div>
                <div className="text-[13px] text-[#6C757D] font-medium mt-0.5">
                  <span className="font-mono tracking-wide text-[#2C2C2C]">{usuario.cod_sis}</span> · {usuario.tipo_institucional}
                </div>
              </div>
              {!usuario.rol_actual && (
                <span className="flex-none flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#FFF4D6] text-[#7A5B00] text-[12px] font-bold">
                  <div className="w-2 h-2 rounded-full border-[2.5px] border-[#7A5B00]"></div> Sin rol
                </span>
              )}
            </div>

            {/* A3.5: Alerta si la cuenta está deshabilitada */}
            {usuario.estado === 'INACTIVO' && (
              <div className="flex gap-3 p-4 bg-[#E4EDF9] border border-[#BDD3EE] text-[#1D4E89] rounded-2xl text-[13px] leading-relaxed">
                <Lock className="w-5 h-5 flex-none mt-0.5" />
                <div>
                  <span className="font-bold block mb-1">La cuenta está deshabilitada</span>
                  Asignar o cambiar el rol no la habilita. Seguirá sin poder ingresar hasta que se habilite.
                </div>
              </div>
            )}

            {errorMsg && (
              <div className="flex items-start gap-3 p-4 bg-[#FDE2E1] border border-[#F4B7B1] text-[#A21B12] rounded-2xl text-[13px] font-bold">
                <AlertTriangle size={18} className="mt-0.5 flex-none" /> {errorMsg}
              </div>
            )}

            {/* Catálogo de Roles */}
            <div className="space-y-3">
              <div className="text-[14px] font-semibold text-[#2C2C2C] mb-2">Nuevo rol <span className="text-[#D92D20]">*</span></div>
              
              {isLoadingRoles ? (
                <div className="flex justify-center p-8 text-[#8A969B]"><Loader2 className="animate-spin" size={24} /></div>
              ) : rolesBd.length === 0 ? (
                <div className="p-6 text-center text-[14px] text-[#6C757D] bg-[#F7FAFA] rounded-2xl border border-dashed border-[#C7D2D3]">
                  No hay roles configurados en el sistema.
                </div>
              ) : (
                rolesBd.map((rol) => {
                  const config = UI_CONFIG[rol.nombre_rol] || { icon: User, requiere: 'Cualquiera' };
                  const isCompatible = config.requiere.toLowerCase() === usuario.tipo_institucional.toLowerCase();
                  const isSelected = selectedRol === rol.id_rol;
                  const isCurrent = usuario.rol_actual === rol.id_rol;

                  return (
                    <label key={rol.id_rol} className={`relative flex items-start gap-4 p-5 rounded-2xl border-[1.5px] transition-all ${
                        isCurrent ? 'border-[#EAF1F1] bg-[#F7FAFA] opacity-70 cursor-not-allowed'
                        : !isCompatible ? 'border-[#EAF1F1] bg-[#F7FAFA] cursor-not-allowed opacity-70'
                        : isSelected ? 'border-[#005E68] bg-[#E9EEEE]/40 cursor-pointer shadow-sm ring-1 ring-[#005E68]/10'
                        : 'border-[#DDDDDD] hover:border-[#C7D2D3] hover:bg-gray-50 cursor-pointer bg-white'
                      }`}
                    >
                      <div className="mt-0.5 flex-none">
                        <div className={`w-[22px] h-[22px] rounded-full border-2 flex items-center justify-center ${isSelected ? 'border-[#005E68]' : 'border-[#C7D2D3] bg-white'}`}>
                          {isSelected && <div className="w-2.5 h-2.5 rounded-full bg-[#005E68]"></div>}
                        </div>
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1.5 flex-wrap">
                          <config.icon size={18} className={isSelected ? 'text-[#005E68]' : 'text-[#6C757D]'} />
                          <span className="font-bold text-[15px] text-[#2C2C2C]">{rol.nombre_rol}</span>
                          
                          {isCurrent && (
                            <span className="px-2 py-0.5 rounded bg-[#EEF2F2] text-[#4F5B62] text-[11px] uppercase tracking-wider font-bold">Rol actual</span>
                          )}
                          {isCompatible && !usuario.rol_actual && config.requiere === 'Docente' && (
                            <span className="px-2 py-0.5 rounded bg-[#E4EDF9] text-[#1D4E89] text-[12px] font-bold flex items-center gap-1">
                              <CheckCircle2 size={12} strokeWidth={3} /> Propuesto por el SIS
                            </span>
                          )}
                        </div>
                        <p className="text-[13px] text-[#6C757D] leading-relaxed mb-3">{rol.descripcion}</p>
                        
                        {!isCompatible ? (
                          <div className="flex items-start gap-1.5 text-[12px] text-[#A21B12] font-semibold bg-[#FDE2E1]/50 p-2.5 rounded-xl border border-[#F2B8B3]/30">
                            <Lock size={14} className="mt-0.5 flex-none" />
                            <span>No disponible: el SIS registra a esta persona como {usuario.tipo_institucional.toLowerCase()}. Requiere {config.requiere.toLowerCase()}.</span>
                          </div>
                        ) : (
                          <div className="flex items-center gap-1.5 text-[12px] text-[#8A969B] font-medium">
                            <CheckCircle2 size={14} /> Requiere tipo institucional: {config.requiere}
                          </div>
                        )}
                      </div>
                      <input type="radio" name="rol" className="hidden" disabled={!isCompatible || isCurrent} checked={isSelected} onChange={() => setSelectedRol(rol.id_rol)} />
                    </label>
                  );
                })
              )}
            </div>
          </div>

          <div className="p-4 px-6 border-t border-[#DDDDDD] bg-[#FBFCFC] flex justify-between items-center rounded-b-[20px]">
            {/* A3.5: Aviso visual si escogen el mismo rol */}
            {isSinCambios && usuario.rol_actual ? (
              <span className="text-[13px] text-[#6C757D] font-medium flex items-center gap-1.5"><Info size={16}/> Eligió el rol que ya tiene. No hay cambios que guardar.</span>
            ) : <div/>}

            <div className="flex gap-3">
              <button onClick={handleClose} className="h-10 px-4 text-[14px] font-semibold text-[#2C2C2C] bg-white border border-[#C7D2D3] rounded-[10px] hover:bg-gray-50 transition-colors">
                Cancelar
              </button>
              
              <button
                onClick={handleContinuar}
                disabled={isSubmitting || !selectedRol || isSinCambios}
                className={`h-10 px-4 text-[14px] font-semibold rounded-[10px] flex items-center gap-2 transition-colors ${
                  isSinCambios || !selectedRol || isSubmitting ? 'bg-[#EEF2F2] text-[#8A969B] cursor-not-allowed' : 'bg-[#005E68] text-white hover:bg-[#004B53] shadow-sm'
                }`}
              >
                {isSubmitting ? 'Procesando...' 
                 : isSinCambios ? 'Sin cambios' 
                 : usuario.rol_actual ? 'Continuar →' 
                 : 'Asignar rol'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ---------------- PANTALLA A3.4 (Confirmación de Impacto) ---------------- */}
      {step === 'confirm' && (
        <div className="bg-white rounded-[24px] shadow-2xl w-full max-w-[480px] flex flex-col overflow-hidden">
          <div className="flex justify-center pt-8 pb-5 bg-gradient-to-b from-[#F7FAFA] to-white">
            <div className="w-14 h-14 bg-[#FFF4D6] text-[#B07F00] rounded-full flex items-center justify-center border border-[#F5DC8C] shadow-sm">
              <ArrowRightLeft size={24} />
            </div>
          </div>
          
          <div className="px-8 pb-8 space-y-6 text-center">
            <h2 className="text-[22px] font-bold text-[#2C2C2C] leading-tight">¿Cambiar el rol de {usuario.nombre}?</h2>
            
            <div className="flex items-center justify-center gap-4 text-[14px] font-bold bg-[#F7FAFA] py-3 rounded-2xl border border-[#DDDDDD]">
              <span className="text-[#6C757D]">{getRolName(usuario.rol_actual)}</span>
              <ArrowRightLeft size={16} className="text-[#C7D2D3]" />
              <span className="text-[#005E68]">{getRolName(selectedRol)}</span>
            </div>

            {impactData?.tiene_activas && (
              <div className="bg-[#FFF4D6] border border-[#F5DC8C] text-[#7A5B00] p-5 rounded-2xl text-[13px] text-left shadow-sm">
                <div className="flex items-center gap-2 font-bold mb-3 text-[14px]">
                  <AlertTriangle size={18} className="text-[#B07F00]" /> Tiene asignaciones activas como {getRolName(usuario.rol_actual)}
                </div>
                <ul className="list-disc pl-6 space-y-1.5 mb-4 font-medium opacity-90">
                  <li>{impactData.grupos} grupos en el periodo actual.</li>
                </ul>
                <p className="opacity-90">Al cambiar de rol dejará de poder gestionarlos. Reasígnelos antes o después del cambio.</p>
              </div>
            )}

            <p className="text-[13px] text-[#6C757D] text-left font-medium bg-[#F7FAFA] p-4 rounded-2xl border border-[#EAF1F1]">
              Si tiene una sesión activa, el nuevo rol se aplicará en su siguiente inicio de sesión.
            </p>
          </div>

          <div className="p-5 border-t border-[#DDDDDD] bg-[#FBFCFC] flex justify-end gap-3">
            <button onClick={() => setStep('select')} disabled={isSubmitting} className="h-10 px-5 text-[14px] font-semibold text-[#2C2C2C] bg-white border border-[#C7D2D3] rounded-[10px] hover:bg-gray-50 transition-colors">
              Cancelar
            </button>
            <button onClick={handleSubmitFinal} disabled={isSubmitting} className="h-10 px-5 text-[14px] font-semibold text-white bg-[#005E68] rounded-[10px] flex items-center gap-2 hover:bg-[#004B53] shadow-sm transition-colors">
              {isSubmitting ? 'Guardando...' : <><ArrowRightLeft size={16} /> Cambiar rol</>}
            </button>
          </div>
        </div>
      )}

    </div>
  );
};