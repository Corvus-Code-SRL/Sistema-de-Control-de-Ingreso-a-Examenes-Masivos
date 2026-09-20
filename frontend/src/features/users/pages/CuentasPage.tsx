import React, { useState } from 'react';
import { CheckCircle, X, Search, ChevronDown, GraduationCap, User, Settings, MoreVertical } from 'lucide-react';
import { RegistrarCuentaModal } from '../components/RegistrarCuentaModal';
import { AsignarRolModal } from '../components/AsignarRolModal';

// Simulación de datos para la tabla (HU-002 mínima)
const mockCuentas = [
  { id_usuario: '1', nombre: 'Laura', paterno: 'Mendoza', materno: 'Rivas', cod_sis: '201600845', correo: 'l.mendoza@umss.edu', tipo_institucional: 'Docente', rol_actual: null },
  { id_usuario: '2', nombre: 'Mateo', paterno: 'Quiroga', materno: 'Salinas', cod_sis: '202103377', correo: '202103377@est.umss.edu', tipo_institucional: 'Estudiante', rol_actual: null },
  { id_usuario: '3', nombre: 'Pablo', paterno: 'Careaga', materno: 'Rojas', cod_sis: '199800412', correo: 'p.careaga@umss.edu', tipo_institucional: 'Docente', rol_actual: 2, nombre_rol: 'Docente' },
];

export const CuentasPage: React.FC = () => {
  // Estados para Registro de Cuenta (HU-001)
  const [isRegistroOpen, setIsRegistroOpen] = useState(false);
  const [toastData, setToastData] = useState<any>(null);

  // Estados para Asignación de Rol (HU-004)
  const [isAsignarRolOpen, setIsAsignarRolOpen] = useState(false);
  const [usuarioSeleccionado, setUsuarioSeleccionado] = useState<any>(null);

  const handleRegistroExitoso = (userData: any) => {
    setToastData(userData);
    setTimeout(() => setToastData(null), 5000);
  };

  const handleAbrirAsignacion = (usuario: any) => {
    setUsuarioSeleccionado({
      id_usuario: usuario.id_usuario,
      nombre: usuario.nombre,
      paterno: usuario.paterno,
      materno: usuario.materno,
      cod_sis: usuario.cod_sis,
      tipo_institucional: usuario.tipo_institucional,
      rol_actual: usuario.rol_actual
    });
    setIsAsignarRolOpen(true);
  };

  return (
    <div className="p-4 md:p-8 relative h-full flex flex-col gap-6">
      
      {/* TOAST DE ÉXITO (Registro) */}
      {toastData && (
        <div className="absolute top-4 right-4 z-40 flex gap-3 items-start w-[380px] p-3.5 bg-white border border-[#DDDDDD] border-l-4 border-l-[#15803D] rounded-[10px] shadow-[0_12px_32px_rgba(5,56,62,0.16)] animate-in fade-in slide-in-from-top-4 duration-300">
          <CheckCircle className="w-5 h-5 text-[#15803D] shrink-0 mt-0.5" />
          <div className="grow flex flex-col">
            <span className="font-semibold text-[14px] text-[#2C2C2C]">Cuenta creada</span>
            <span className="text-[13px] text-[#6C757D] leading-[18px] mt-0.5">
              {toastData.nombre} {toastData.paterno} quedó sin rol. Asígnele uno para que pueda ingresar.
            </span>
          </div>
          <button onClick={() => setToastData(null)} className="text-[#8A969B] hover:text-[#4F5B62] shrink-0">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Cabecera */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-[28px] leading-[36px] font-semibold text-[#2C2C2C] tracking-tight">Cuentas</h1>
          <p className="text-[#6C757D] text-[14px] mt-1">Personas con acceso a SCIEM. El tipo institucional viene del SIS y define qué roles puede recibir cada cuenta.</p>
        </div>
        
        <button 
          onClick={() => setIsRegistroOpen(true)} 
          className="h-10 px-4 rounded-[10px] bg-[#005E68] text-white font-semibold flex items-center gap-2 hover:bg-[#004B53] shrink-0"
        >
          Registrar cuenta
        </button>
      </div>

      {/* Filtros (Solo visuales por ahora) */}
      <div className="flex flex-col gap-4">
        <div className="flex gap-1 border-b border-[#DDDDDD]">
          <span className="h-10 px-3.5 flex items-center gap-2 text-[14px] font-semibold text-[#005E68] border-b-2 border-[#005E68] mb-[-1px]">
            Todas <span className="bg-[#D8ECEE] text-[#05383E] px-1.5 py-0.5 rounded-full text-xs">3</span>
          </span>
          <span className="h-10 px-3.5 flex items-center gap-2 text-[14px] font-medium text-[#6C757D] cursor-pointer">
            Sin rol <span className="bg-[#EEF2F2] text-[#4F5B62] px-1.5 py-0.5 rounded-full text-xs">2</span>
          </span>
        </div>

        <div className="flex flex-col sm:flex-row gap-3">
          <div className="relative grow">
            <Search className="absolute left-3 top-2.5 w-4 h-4 text-[#8A969B]" />
            <input type="text" placeholder="Buscar por nombre, código SIS o correo" className="w-full h-10 pl-9 pr-3 rounded-lg border border-[#C7D2D3] text-[14px] focus:outline-none focus:border-[#005E68] focus:ring-4 focus:ring-[#005E68]/10" />
          </div>
          <button className="h-10 px-3 flex items-center justify-between gap-2 border border-[#C7D2D3] rounded-lg bg-white text-[#2C2C2C] sm:w-[190px]">
            <span className="text-[14px]">Todos los roles</span>
            <ChevronDown className="w-4 h-4 text-[#6C757D]" />
          </button>
          <button className="h-10 px-3 flex items-center justify-between gap-2 border border-[#C7D2D3] rounded-lg bg-white text-[#2C2C2C] sm:w-[190px]">
            <span className="text-[14px]">Todos los tipos</span>
            <ChevronDown className="w-4 h-4 text-[#6C757D]" />
          </button>
        </div>
      </div>

      {/* Tabla (Desktop) / Tarjetas (Móvil) */}
      <div className="bg-white border border-[#DDDDDD] rounded-xl overflow-hidden shadow-sm">
        {/* Vista Móvil (Oculta en md) */}
        <div className="block md:hidden">
          {mockCuentas.map((cuenta) => (
            <div key={cuenta.id_usuario} className="p-4 border-b border-[#DDDDDD] flex flex-col gap-3">
              <div className="flex justify-between items-start">
                <div>
                  <div className="font-semibold text-[#2C2C2C]">{cuenta.nombre} {cuenta.paterno}</div>
                  <div className="text-[13px] text-[#6C757D]">{cuenta.cod_sis} · {cuenta.tipo_institucional}</div>
                </div>
                {cuenta.rol_actual ? (
                  <span className="px-2 py-1 bg-[#D8ECEE] text-[#05383E] rounded-full text-xs font-semibold">{cuenta.nombre_rol}</span>
                ) : (
                  <span className="px-2 py-1 bg-[#FFF4D6] text-[#7A5B00] rounded-full text-xs font-semibold flex items-center gap-1">
                     Sin rol
                  </span>
                )}
              </div>
              <div className="flex justify-end mt-2">
                {!cuenta.rol_actual ? (
                  <button onClick={() => handleAbrirAsignacion(cuenta)} className="h-8 px-3 rounded-lg bg-[#005E68] text-white text-[13px] font-semibold w-full">
                    Asignar rol
                  </button>
                ) : (
                  <button className="h-8 px-3 text-[#6C757D] bg-[#F3F8F8] border border-[#DDDDDD] rounded-lg w-full font-medium">Ver detalle</button>
                )}
              </div>
            </div>
          ))}
        </div>

        {/* Vista Desktop (Oculta en móvil) */}
        <div className="hidden md:block overflow-x-auto">
          <table className="w-full text-left text-[14px]">
            <thead className="bg-[#F7FAFA] border-b border-[#DDDDDD] text-[11px] font-semibold text-[#6C757D] uppercase tracking-[0.08em]">
              <tr>
                <th className="px-4 py-3 h-11 whitespace-nowrap">Persona</th>
                <th className="px-4 py-3 h-11 whitespace-nowrap">Código SIS</th>
                <th className="px-4 py-3 h-11 whitespace-nowrap">Tipo Institucional</th>
                <th className="px-4 py-3 h-11 whitespace-nowrap">Rol</th>
                <th className="px-4 py-3 h-11 whitespace-nowrap text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {mockCuentas.map((cuenta) => (
                <tr key={cuenta.id_usuario} className="border-b border-[#DDDDDD] hover:bg-[#F7FAFA]">
                  <td className="px-4 py-3 h-[52px]">
                    <div className="flex flex-col">
                      <span className="font-semibold text-[#2C2C2C]">{cuenta.nombre} {cuenta.paterno} {cuenta.materno}</span>
                      <span className="text-[12px] text-[#6C757D]">{cuenta.correo}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3 font-semibold font-mono text-[#2C2C2C] tracking-wide">{cuenta.cod_sis}</td>
                  <td className="px-4 py-3 text-[#6C757D] flex items-center gap-1.5 pt-4">
                     {cuenta.tipo_institucional}
                  </td>
                  <td className="px-4 py-3">
                    {cuenta.rol_actual ? (
                      <span className="px-2.5 py-1 bg-[#D8ECEE] text-[#05383E] rounded-full text-[12px] font-semibold">{cuenta.nombre_rol}</span>
                    ) : (
                      <span className="px-2.5 py-1 bg-[#FFF4D6] text-[#7A5B00] rounded-full text-[12px] font-semibold flex items-center gap-1 w-fit">
                        Sin rol
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    {!cuenta.rol_actual ? (
                      <button 
                        onClick={() => handleAbrirAsignacion(cuenta)}
                        className="h-8 px-3 rounded-lg bg-[#005E68] text-white text-[13px] font-semibold inline-flex items-center hover:bg-[#004B53]"
                      >
                        Asignar rol
                      </button>
                    ) : (
                      <button className="h-8 w-8 rounded-lg border border-[#C7D2D3] bg-white inline-flex items-center justify-center text-[#4F5B62] hover:bg-gray-50">
                         <MoreVertical size={16} />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Modales */}
      <RegistrarCuentaModal 
        isOpen={isRegistroOpen} 
        onClose={() => setIsRegistroOpen(false)} 
        onSuccess={(data) => {
          setIsRegistroOpen(false);
          handleRegistroExitoso(data);
        }}
      />

      <AsignarRolModal 
        isOpen={isAsignarRolOpen}
        onClose={() => setIsAsignarRolOpen(false)}
        onSuccess={() => {
          setIsAsignarRolOpen(false);
          alert("Rol asignado correctamente");
        }}
        usuario={usuarioSeleccionado}
      />
    </div>
  );
};