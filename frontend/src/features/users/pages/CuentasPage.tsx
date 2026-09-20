import React, { useState } from 'react';
import { CheckCircle, X } from 'lucide-react';
import { RegistrarCuentaModal } from '../components/RegistrarCuentaModal';

export const CuentasPage: React.FC = () => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [toastData, setToastData] = useState<any>(null);

  const handleRegistroExitoso = (userData: any) => {
    setToastData(userData);
    setTimeout(() => setToastData(null), 5000);
  };

  return (
    <div className="p-4 md:p-8 relative h-full">
      
      {/* TOAST DE ÉXITO */}
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

      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold text-[#2C2C2C]">Cuentas</h1>
          <p className="text-[#6C757D] text-sm mt-1">Personas con acceso a SCIEM. El tipo institucional viene del SIS...</p>
        </div>
        
        <button 
          onClick={() => setIsModalOpen(true)} 
          className="h-10 px-4 rounded-[10px] bg-[#005E68] text-white font-semibold flex items-center gap-2 hover:bg-[#004B53]"
        >
          Registrar cuenta
        </button>
      </div>

      <div className="bg-white border border-[#DDDDDD] rounded-xl p-8 text-center text-[#6C757D]">
        Aquí irá la tabla de cuentas (HU-002) más adelante.
      </div>

      <RegistrarCuentaModal 
        isOpen={isModalOpen} 
        onClose={() => setIsModalOpen(false)} 
        onSuccess={handleRegistroExitoso}
      />
    </div>
  );
};