import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Edit2, Lock, GraduationCap, UserCircle, Loader2, CheckCircle2, X } from 'lucide-react';
import { AsignarRolModal } from '../components/AsignarRolModal';

export const CuentaDetallePage: React.FC = () => {
  const { id } = useParams<{ id: string }>(); 
  const navigate = useNavigate();
  
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [usuarioDetalle, setUsuarioDetalle] = useState<any>(null);
  
  // Estado para controlar el Toast de éxito (Pantalla A3.6)
  const [toastMessage, setToastMessage] = useState<{titulo: string, descripcion: string} | null>(null);

  const ID_ADMINISTRADOR_ACTUAL = 'uuid-del-admin-logueado'; 

  const fetchDetalleCuenta = async () => {
    setIsLoading(true);
    try {
      const resUser = await fetch(`/api/usuarios/${id}`, { headers: { 'Accept': 'application/json' }});
      const resRol = await fetch(`/api/usuarios/${id}/rol`, { headers: { 'Accept': 'application/json' }});

      if (resUser.ok) {
        const dataUser = await resUser.json();
        const dataRol = resRol.ok ? await resRol.json() : null;

        setUsuarioDetalle({
          ...dataUser.data,
          rol_vigente: dataRol?.data || null 
        });
      }
    } catch (error) {
      console.error("Error de conexión:", error);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (id) {
      fetchDetalleCuenta();
    }
  }, [id]);

  if (isLoading) {
    return (
      <div className="flex-1 p-8 flex justify-center items-center bg-[#F3F8F8] min-h-screen">
        <Loader2 className="w-8 h-8 animate-spin text-[#005E68]" />
      </div>
    );
  }

  if (!usuarioDetalle) {
    return (
      <div className="flex-1 p-8 text-center bg-[#F3F8F8] min-h-screen">
        <h2 className="text-xl font-bold text-gray-700">Cuenta no encontrada</h2>
        <button onClick={() => navigate('/cuentas')} className="mt-4 text-[#005E68] font-semibold hover:underline">Volver a Cuentas</button>
      </div>
    );
  }

  const iniciales = `${usuarioDetalle.nombre.charAt(0)}${usuarioDetalle.paterno.charAt(0)}`.toUpperCase();
  const esSuPropiaCuenta = usuarioDetalle.id_usuario === ID_ADMINISTRADOR_ACTUAL;

  return (
    <div className="flex-1 p-4 sm:p-8 bg-[#F3F8F8] min-h-screen relative">
      
      {/* ---------------- TOAST DE ÉXITO (A3.6) ---------------- */}
      {toastMessage && (
        <div className="fixed top-6 right-6 z-50 flex gap-3 items-start w-[380px] p-4 bg-white border border-[#DDDDDD] border-l-4 border-l-[#15803D] rounded-xl shadow-[0_12px_32px_rgba(5,56,62,0.16)] animate-in fade-in slide-in-from-top-4 duration-300">
          <CheckCircle2 className="w-5 h-5 text-[#15803D] shrink-0 mt-0.5" />
          <div className="grow flex flex-col">
            <span className="font-bold text-[14px] text-[#2C2C2C]">{toastMessage.titulo}</span>
            <span className="text-[13px] text-[#6C757D] leading-relaxed mt-0.5">
              {toastMessage.descripcion}
            </span>
          </div>
          <button onClick={() => setToastMessage(null)} className="text-[#8A969B] hover:text-[#4F5B62] shrink-0 p-1">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      <button 
        onClick={() => navigate('/cuentas')}
        className="flex items-center gap-2 text-[#005E68] font-semibold text-[13px] mb-4 hover:underline"
      >
        <ArrowLeft size={16} /> Cuentas
      </button>

      <h1 className="text-[28px] leading-[36px] font-semibold text-[#2C2C2C] tracking-tight mb-6">Detalle de la cuenta</h1>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div className="col-span-1 lg:col-span-2 space-y-5">
          
          {/* TARJETA 1: INFO GENERAL */}
          <div className="bg-white p-5 sm:p-6 rounded-2xl border border-[#DDDDDD] flex flex-col sm:flex-row sm:items-center gap-4 justify-between shadow-sm">
            <div className="flex items-center gap-4">
              <div className="w-14 h-14 bg-[#D8ECEE] text-[#05383E] text-xl font-bold rounded-full flex items-center justify-center flex-none">
                {iniciales}
              </div>
              <div className="min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <h2 className="text-[20px] leading-tight font-bold text-[#2C2C2C] truncate">{usuarioDetalle.nombre} {usuarioDetalle.paterno}</h2>
                  <span className={`px-2 py-0.5 text-[12px] font-semibold rounded-full shrink-0 ${usuarioDetalle.estado === 'ACTIVO' ? 'bg-[#DFF1E7] text-[#0F5C2C]' : 'bg-[#F3F5F5] text-[#8A969B]'}`}>
                    {usuarioDetalle.estado === 'ACTIVO' ? 'Activa' : 'Deshabilitada'}
                  </span>
                  
                  {esSuPropiaCuenta && (
                    <span className="px-2 py-0.5 bg-[#E4EDF9] text-[#1D4E89] text-[12px] font-semibold rounded-full shrink-0 flex items-center gap-1">
                      <UserCircle size={14}/> Su cuenta
                    </span>
                  )}
                </div>
                <p className="text-[13px] text-[#6C757D] mt-1 truncate">
                  <span className="font-mono tracking-wide font-semibold text-[#2C2C2C]">{usuarioDetalle.cod_sis}</span> · {usuarioDetalle.correo}
                </p>
              </div>
            </div>
            
            <div className="flex sm:flex-row flex-col gap-2 mt-2 sm:mt-0 shrink-0">
              <button className="flex items-center justify-center gap-2 px-4 py-2 border border-[#C7D2D3] rounded-lg text-[14px] font-semibold text-[#2C2C2C] hover:bg-gray-50">
                <Edit2 size={16} className="text-[#6C757D]" /> Editar datos
              </button>
              <button className="flex items-center justify-center gap-2 px-4 py-2 border border-[#F2B8B3] text-[#A21B12] bg-white rounded-lg text-[14px] font-semibold hover:bg-red-50">
                <Lock size={16} /> Deshabilitar
              </button>
            </div>
          </div>

          {/* TARJETA 2: ROL VIGENTE */}
          <div className="bg-white p-6 rounded-2xl border border-[#DDDDDD] shadow-sm">
            <div className="flex justify-between items-start mb-6">
              <h3 className="text-[16px] font-semibold text-[#2C2C2C]">Rol</h3>
              
              <div className="relative group">
                <button 
                  onClick={() => !esSuPropiaCuenta && setIsModalOpen(true)}
                  disabled={esSuPropiaCuenta}
                  className={`flex items-center gap-2 px-3 py-1.5 border rounded-lg text-[13px] font-semibold transition-colors ${
                    esSuPropiaCuenta 
                      ? 'border-[#DDDDDD] bg-[#F3F5F5] text-[#AAB8BA] cursor-not-allowed' 
                      : 'border-[#C7D2D3] text-[#2C2C2C] hover:bg-gray-50'
                  }`}
                >
                  <ArrowLeft size={16} className={`rotate-180 ${esSuPropiaCuenta ? 'text-[#AAB8BA]' : 'text-[#6C757D]'}`} /> 
                  {usuarioDetalle.rol_vigente ? 'Cambiar rol' : 'Asignar rol'}
                </button>

                {esSuPropiaCuenta && (
                  <div className="absolute right-0 top-full mt-2 w-[260px] p-2 bg-[#05383E] text-white text-[12px] leading-snug rounded-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-10 shadow-lg pointer-events-none">
                    No puede cambiar el rol de su propia cuenta. Pídalo a otro administrador.
                  </div>
                )}
              </div>
            </div>

            {usuarioDetalle.rol_vigente ? (
              <div className="flex items-center gap-3 mb-6">
                <div className="w-10 h-10 bg-[#D8ECEE] text-[#005E68] rounded-xl flex items-center justify-center flex-none">
                  <GraduationCap size={20} />
                </div>
                <div className="min-w-0">
                  <div className="font-bold text-[18px] text-[#2C2C2C]">{usuarioDetalle.rol_vigente.nombre_rol}</div>
                  <div className="text-[13px] text-[#6C757D] truncate">
                    Desde el {new Date(usuarioDetalle.rol_vigente.fecha_inicio || '').toLocaleDateString('es-ES')} · asignado por {usuarioDetalle.rol_vigente.asignado_por || 'Sistema'}
                  </div>
                </div>
              </div>
            ) : (
              <div className="flex items-center gap-3 mb-6 text-[#7A5B00] bg-[#FFF4D6] p-4 rounded-xl">
                <span className="font-semibold text-sm">Esta cuenta no tiene ningún rol asignado.</span>
              </div>
            )}
            
            <div className="border-t border-[#DDDDDD] pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-[14px]">
              <div><span className="text-[#6C757D]">Tipo institucional:</span> <span className="font-medium text-[#2C2C2C]">{usuarioDetalle.tipo_institucional} · según SIS</span></div>
              <div><span className="text-[#6C757D]">Facultad:</span> <span className="font-medium text-[#2C2C2C]">Ciencias y Tecnología</span></div>
            </div>
          </div>

          {/* TARJETA 3: HISTORIAL DE ROLES */}
          <div className="bg-white p-6 rounded-2xl border border-[#DDDDDD] shadow-sm">
            <h3 className="text-[16px] font-semibold text-[#2C2C2C] mb-4">Historial de roles</h3>
            <div className="overflow-x-auto -mx-6 sm:mx-0">
              <table className="w-full text-[14px] text-left">
                <thead className="bg-[#F7FAFA] border-b border-t sm:border-t-0 border-[#DDDDDD] text-[11px] font-semibold text-[#6C757D] uppercase tracking-[0.08em]">
                  <tr>
                    <th className="px-6 sm:px-4 py-3 h-11">Rol</th>
                    <th className="px-4 py-3 h-11">Desde</th>
                    <th className="px-4 py-3 h-11">Hasta</th>
                    <th className="px-6 sm:px-4 py-3 h-11">Asignó</th>
                  </tr>
                </thead>
                <tbody>
                  {usuarioDetalle.rol_vigente ? (
                    <>
                      <tr className="border-b border-[#DDDDDD]">
                        <td className="px-6 sm:px-4 py-3 h-[52px]">
                          <span className="px-2.5 py-1 bg-[#D8ECEE] text-[#05383E] rounded-full text-[12px] font-semibold">{usuarioDetalle.rol_vigente.nombre_rol}</span>
                        </td>
                        <td className="px-4 py-3 text-[#2C2C2C]">{new Date(usuarioDetalle.rol_vigente.fecha_inicio || '').toLocaleDateString('es-ES')}</td>
                        <td className="px-4 py-3">
                          <span className="px-2 py-1 bg-[#DFF1E7] text-[#0F5C2C] rounded-full text-[12px] font-bold flex items-center gap-1 w-fit"><CheckCircle2 size={12} strokeWidth={3}/> Vigente</span>
                        </td>
                        <td className="px-6 sm:px-4 py-3 text-[#2C2C2C]">{usuarioDetalle.rol_vigente.asignado_por || 'Sistema'}</td>
                      </tr>
                      {/* Fila estática para simular el historial anterior como en el diseño */}
                      {usuarioDetalle.rol_vigente.nombre_rol === 'Supervisor de admisión' && (
                        <tr>
                          <td className="px-6 sm:px-4 py-3 h-[52px]">
                            <span className="px-2.5 py-1 bg-[#EEF2F2] text-[#4F5B62] rounded-full text-[12px] font-semibold">Docente</span>
                          </td>
                          <td className="px-4 py-3 text-[#2C2C2C]">02/09/2026</td>
                          <td className="px-4 py-3 text-[#2C2C2C]">{new Date().toLocaleDateString('es-ES')}</td>
                          <td className="px-6 sm:px-4 py-3 text-[#2C2C2C]">R. Guzmán</td>
                        </tr>
                      )}
                    </>
                  ) : (
                    <tr>
                      <td colSpan={4} className="px-6 py-5 text-center text-[13px] text-[#6C757D]">Aún no hay registros en el historial de roles.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {/* COLUMNA DERECHA: ACTIVIDAD */}
        <div className="col-span-1">
          <div className="bg-white p-6 rounded-2xl border border-[#DDDDDD] shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-[16px] font-semibold text-[#2C2C2C]">Actividad de la cuenta</h3>
              <button className="text-[#005E68] text-[13px] font-semibold hover:underline">Ver en la bitácora</button>
            </div>
            <div className="text-[13px] text-[#6C757D] text-center p-4 bg-[#F7FAFA] rounded-xl border border-dashed border-[#C7D2D3]">
              Línea de tiempo en construcción...
            </div>
          </div>
        </div>

      </div>

      <AsignarRolModal 
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSuccess={() => {
          setIsModalOpen(false);
          fetchDetalleCuenta(); // 1. Recarga los datos automáticamente
          
          // 2. Dispara el Toast de éxito (Pantalla A3.6)
          setToastMessage({
            titulo: usuarioDetalle.rol_vigente ? 'Rol actualizado' : 'Rol asignado',
            descripcion: `${usuarioDetalle.nombre} ahora tiene su rol modificado. Rige desde su siguiente acceso.`
          });
          
          // 3. Oculta el Toast después de 5 segundos
          setTimeout(() => setToastMessage(null), 5000);
        }}
        usuario={{
          ...usuarioDetalle,
          rol_actual: usuarioDetalle.rol_vigente?.id_rol
        }}
      />
    </div>
  );
};