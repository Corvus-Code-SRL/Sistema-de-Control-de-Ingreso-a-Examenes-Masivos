import React, { useState } from 'react';
import { 
  Search, Info, X, ChevronLeft, ArrowRight, Pencil, 
  RefreshCw, CheckCircle, GraduationCap, AlertCircle, Lock, Check, Trash2 
} from 'lucide-react';

interface RegistrarCuentaModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: (userData: any) => void;
}

export type ErrorType = 'not_found' | 'duplicate' | 'sis_down' | null;

export const RegistrarCuentaModal: React.FC<RegistrarCuentaModalProps> = ({ isOpen, onClose, onSuccess }) => {
  const [paso, setPaso] = useState<1 | 2 | 'success'>(1);
  const [codSis, setCodSis] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const [sisData, setSisData] = useState<any>(null);
  const [errorType, setErrorType] = useState<ErrorType>(null);
  const [backendErrorMsg, setBackendErrorMsg] = useState('');

  const [correo, setCorreo] = useState('');
  const [telefono, setTelefono] = useState('');
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [showCancelConfirm, setShowCancelConfirm] = useState(false);

  const handleVerificarSIS = async () => {
    setIsVerifying(true);
    setErrorType(null);
    setBackendErrorMsg('');

    try {
      const response = await fetch(`/api/sis/verificar/${codSis}`, {
        headers: { 'Accept': 'application/json' }
      });

      if (response.status === 503) {
        setErrorType('sis_down');
        setIsVerifying(false);
        return;
      }

      const data = await response.json();

      if (response.status === 422) {
        const mensajeError = data.errors?.cod_sis?.[0] || data.message;
        setBackendErrorMsg(mensajeError);

        if (mensajeError.toLowerCase().includes('ya existe')) {
          setErrorType('duplicate');
        } else {
          setErrorType('not_found');
        }
        setIsVerifying(false);
        return;
      }

      if (response.ok) {
        setSisData(data.data);
      }
    } catch (error) {
      setErrorType('sis_down');
    } finally {
      setIsVerifying(false);
    }
  };

  const handleRegistrar = async () => {
    const newErrors: Record<string, string> = {};
    
    if (!correo || !correo.includes('@')) {
      newErrors.correo = 'Ingrese un correo válido, por ejemplo nombre@umss.edu.';
    }
    
    if (telefono && !/^\d{8}$/.test(telefono)) {
      newErrors.telefono = 'El teléfono debe tener 8 dígitos.';
    }

    if (Object.keys(newErrors).length > 0) {
      setFormErrors(newErrors);
      return;
    }

    setIsSubmitting(true);
    setFormErrors({});

    try {
      const response = await fetch('/api/usuarios', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          cod_sis: codSis,
          nombre: sisData.nombre,
          paterno: sisData.paterno,
          materno: sisData.materno,
          correo: correo,
          telefono: telefono,
          tipo_institucional: sisData.tipo,
          facultad: sisData.facultad
        })
      });

      if (response.ok) {
        setPaso('success');
      } else {
        const data = await response.json();
        setFormErrors(data.errors || { general: data.message });
      }
    } catch (error) {
      setFormErrors({ general: 'Error de conexión con el servidor.' });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCerrar = () => {
    if ((sisData || paso === 2) && paso !== 'success') {
      setShowCancelConfirm(true);
    } else {
      cerrarPorCompleto();
    }
  };

  const cerrarPorCompleto = () => {
    setPaso(1);
    setCodSis('');
    setSisData(null);
    setErrorType(null);
    setCorreo('');
    setTelefono('');
    setFormErrors({});
    setShowCancelConfirm(false);
    onClose();
  };

  const hasInputError = errorType === 'not_found' || errorType === 'duplicate';

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex flex-col md:items-center md:justify-center bg-white md:bg-[#101C1E]/55 md:p-4">
      <div className="flex flex-col bg-white w-full h-full md:h-auto md:max-h-[90vh] md:w-[640px] md:rounded-[14px] md:shadow-[0_12px_32px_rgba(5,56,62,0.16)] overflow-hidden relative">
        
        {showCancelConfirm && (
          <div className="absolute inset-0 z-50 bg-[#101C1E]/40 backdrop-blur-[2px] flex items-center justify-center p-4">
            <div className="bg-white rounded-[14px] shadow-[0_12px_32px_rgba(5,56,62,0.16)] w-full max-w-[440px] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
              <div className="flex items-center gap-3 px-6 pt-5 pb-4">
                <span className="w-10 h-10 rounded-full bg-[#FDE2E1] text-[#D92D20] flex items-center justify-center shrink-0">
                  <Trash2 className="w-5 h-5" />
                </span>
                <h3 className="text-[18px] font-semibold text-[#2C2C2C]">¿Descartar el registro?</h3>
              </div>
              <div className="px-6 pb-6 text-[14px] text-[#4F5B62] leading-[20px]">
                No se creará la cuenta de <b className="font-semibold text-[#2C2C2C]">{sisData?.nombre} {sisData?.paterno} {sisData?.materno}</b> y se perderán los datos ingresados.
              </div>
              <div className="p-4 border-t border-[#DDDDDD] bg-[#FBFCFC] flex flex-col-reverse md:flex-row justify-end gap-3">
                <button onClick={() => setShowCancelConfirm(false)} className="h-10 px-4 w-full md:w-auto rounded-[10px] border border-[#C7D2D3] bg-white text-[#2C2C2C] font-semibold text-[14px] hover:bg-[#F7FAFA] transition-colors">
                  Seguir registrando
                </button>
                <button onClick={cerrarPorCompleto} className="h-10 px-4 w-full md:w-auto rounded-[10px] bg-[#D92D20] text-white font-semibold text-[14px] hover:bg-[#B3251A] transition-colors">
                  Descartar
                </button>
              </div>
            </div>
          </div>
        )}

        <div className="md:hidden flex items-center gap-2 h-14 px-2 border-b border-[#DDDDDD] bg-white shrink-0">
          <button onClick={handleCerrar} className="w-10 h-10 flex items-center justify-center text-[#4F5B62] rounded-[8px]">
            <ChevronLeft className="w-5 h-5" />
          </button>
          <div className="flex-1 font-semibold text-[16px] text-[#2C2C2C] truncate">Registrar cuenta</div>
        </div>

        <div className="hidden md:flex items-start justify-between px-6 pt-5 pb-1 shrink-0">
          <div className="flex-1 min-w-0">
            <h2 className="text-[18px] leading-[26px] font-semibold text-[#2C2C2C]">Registrar cuenta</h2>
            <p className="text-[13px] text-[#6C757D] leading-[19px] mt-0.5">
              {paso === 1 ? 'Paso 1 de 2' : paso === 2 ? 'Paso 2 de 2' : 'Registrada a las 09:14 · queda en la bitácora'}
            </p>
          </div>
          <button onClick={handleCerrar} className="w-8 h-8 flex items-center justify-center text-[#6C757D] hover:bg-[#F3F8F8] rounded-[8px] transition-colors">
            <X className="w-4 h-4" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 md:px-6 md:py-4 flex flex-col gap-4">
          
          {paso !== 'success' && (
            <div className="hidden md:flex items-center gap-2 mb-2">
              <span className={`flex items-center gap-2 text-[13px] ${paso === 1 ? 'font-semibold text-[#2C2C2C]' : 'font-semibold text-[#005E68]'}`}>
                <span className={`w-6 h-6 rounded-full flex items-center justify-center text-[12px] ${paso === 1 ? 'bg-[#005E68] text-white' : 'bg-[#D8ECEE] text-[#005E68]'}`}>
                  {paso === 1 ? '1' : <Check className="w-3.5 h-3.5 stroke-[3]" />}
                </span>
                <span>Código SIS</span>
              </span>
              <span className="flex-1 h-[1px] bg-[#C7D2D3] min-w-[16px]"></span>
              <span className={`flex items-center gap-2 text-[13px] ${paso === 2 ? 'font-semibold text-[#2C2C2C]' : 'font-medium text-[#6C757D]'}`}>
                <span className={`w-6 h-6 rounded-full border-[1.5px] flex items-center justify-center text-[12px] font-bold ${paso === 2 ? 'bg-[#005E68] border-[#005E68] text-white' : 'border-[#C7D2D3] bg-white'}`}>
                  2
                </span>
                <span>Datos de la cuenta</span>
              </span>
            </div>
          )}

          {paso === 1 && (
            <>
              <div className="flex flex-col md:flex-row items-start md:items-start gap-3 md:gap-4">
                <div className="flex flex-col gap-1.5 w-full md:flex-1">
                  <label className="text-[14px] font-medium text-[#2C2C2C] flex items-center gap-1">
                    Código SIS <span className="text-[#D92D20]">*</span>
                  </label>
                  
                  <div className={`flex items-center gap-2 h-10 px-3 rounded-[8px] border bg-white transition-all ${
                    hasInputError
                      ? 'border-[#D92D20] shadow-[0_0_0_3px_rgba(217,45,32,0.12)] text-[#D92D20]'
                      : sisData || isVerifying 
                        ? 'border-[#C7D2D3]' 
                        : 'border-[#C7D2D3] focus-within:border-[#005E68] focus-within:shadow-[0_0_0_2px_#fff,0_0_0_4px_rgba(0,94,104,0.30)]'
                  }`}>
                    <span className={`${hasInputError ? 'text-[#D92D20]' : 'text-[#8A969B]'} font-medium select-none`}>#</span>
                    <input 
                      type="text" 
                      value={codSis}
                      onChange={(e) => {
                        setCodSis(e.target.value);
                        setErrorType(null); 
                      }}
                      placeholder="9 dígitos"
                      disabled={isVerifying || !!sisData}
                      className="flex-1 w-full bg-transparent outline-none text-[14px] placeholder:text-[#8A969B] disabled:bg-transparent"
                      maxLength={9}
                    />
                  </div>
                  
                  {errorType === 'not_found' && (
                    <div className="text-[13px] text-[#D92D20] font-medium flex gap-1.5 mt-0.5">
                      <AlertCircle className="w-[18px] h-[18px] shrink-0" />
                      <span>{backendErrorMsg || 'No se encontró a ninguna persona con este código en el SIS. Revise el número.'}</span>
                    </div>
                  )}

                  {errorType === 'duplicate' && (
                    <div className="flex flex-col gap-1 mt-0.5">
                      <div className="text-[13px] text-[#D92D20] font-medium flex gap-1.5">
                        <AlertCircle className="w-[18px] h-[18px] shrink-0" />
                        <span>{backendErrorMsg}</span>
                      </div>
                      <button className="text-[13px] font-semibold text-[#005E68] text-left hover:underline w-max">
                        Ver la cuenta existente
                      </button>
                    </div>
                  )}

                  {!hasInputError && (
                    <div className="text-[13px] text-[#6C757D] leading-[18px]">
                      {sisData ? 'Verificado a las 09:14.' : 'Se consulta en la fuente institucional antes de continuar.'}
                    </div>
                  )}
                </div>

                {sisData ? (
                  <button onClick={() => { setSisData(null); setCodSis(''); }} className="mt-7 w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] text-[#005E68] text-[14px] font-semibold hover:bg-[#F3F8F8] transition-colors shrink-0">
                    <Pencil className="w-4 h-4" /> Cambiar
                  </button>
                ) : (
                  <button onClick={handleVerificarSIS} disabled={isVerifying || codSis.trim() === ''} className="mt-7 w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] border border-[#C7D2D3] bg-white text-[#2C2C2C] text-[14px] font-semibold hover:bg-[#F7FAFA] transition-colors shrink-0 disabled:opacity-50 disabled:cursor-not-allowed">
                    {isVerifying ? <><RefreshCw className="w-4 h-4 animate-spin text-[#8A969B]" /> <span className="text-[#8A969B]">Verificando...</span></> : <><Search className="w-4 h-4" /> Verificar en el SIS</>}
                  </button>
                )}
              </div>

              {errorType === 'sis_down' && (
                <div className="mt-2 flex gap-3 p-4 rounded-[12px] bg-[#FDE2E1] border border-[#F4B7B1] text-[#D92D20]">
                  <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                  <div className="flex flex-col gap-3 w-full">
                    <div className="flex flex-col">
                      <span className="font-semibold text-[14px]">El SIS no está disponible</span>
                      <span className="text-[13px] opacity-90 mt-0.5 leading-[19px]">No se puede validar a la persona en este momento, así que el registro no puede continuar. Intente de nuevo en unos minutos.</span>
                    </div>
                    <button onClick={handleVerificarSIS} className="w-max h-8 px-3 flex items-center justify-center gap-2 rounded-[8px] border border-[#C7D2D3] bg-white text-[#2C2C2C] text-[13px] font-semibold hover:bg-[#F7FAFA] transition-colors">
                      <RefreshCw className="w-3.5 h-3.5" /> Reintentar
                    </button>
                  </div>
                </div>
              )}

              {isVerifying && (
                <div className="mt-2 flex flex-col gap-2 p-4 rounded-[12px] border border-[#DDDDDD] bg-[#F7FAFA]">
                  <span className="text-[13px] text-[#6C757D]">Consultando la fuente institucional...</span>
                  <div className="h-1.5 bg-[#E3ECEC] rounded-full overflow-hidden">
                    <div className="h-full bg-[#005E68] rounded-full w-[55%] animate-pulse"></div>
                  </div>
                </div>
              )}

              {sisData && (
                <div className="mt-2 flex flex-col gap-3 p-4 rounded-[12px] border border-[#B6DEC6] bg-[#F6FBF8]">
                  <div className="flex items-center gap-2 text-[#0F5C2C]">
                    <CheckCircle className="w-[18px] h-[18px]" />
                    <span className="font-semibold text-[14px]">Persona reconocida por el SIS</span>
                  </div>
                  <div className="flex items-center gap-3.5">
                    <div className="w-14 h-14 rounded-full bg-[#D8ECEE] text-[#05383E] flex items-center justify-center font-bold text-[18px] shrink-0">
                      {sisData.nombre.charAt(0)}{sisData.paterno.charAt(0)}
                    </div>
                    <div className="flex flex-col gap-1">
                      <span className="font-semibold text-[18px] leading-[28px] text-[#2C2C2C]">
                        {sisData.nombre} {sisData.paterno} {sisData.materno}
                      </span>
                      <div className="flex flex-wrap items-center gap-2 mt-0.5">
                        <span className="px-2.5 py-0.5 rounded-full bg-[#E4EDF9] text-[#1D4E89] text-[12px] font-semibold flex items-center gap-1.5 border border-[#BDD3EE]">
                          <GraduationCap className="w-3.5 h-3.5" />
                          Tipo institucional: {sisData.tipo}
                        </span>
                        <span className="text-[13px] text-[#6C757D] leading-tight max-w-[120px] md:max-w-none">{sisData.facultad}</span>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {!isVerifying && !sisData && !errorType && (
                <div className="mt-2 flex gap-3 p-3 md:py-3 md:px-4 rounded-[10px] bg-[#E4EDF9] border border-[#BDD3EE] text-[#1D4E89]">
                  <Info className="w-5 h-5 shrink-0 mt-0.5" />
                  <div className="flex flex-col">
                    <span className="font-semibold text-[14px]">Solo personas reconocida por la institución</span>
                    <span className="text-[13px] opacity-90 mt-0.5 leading-[19px]">El SIS devuelve el nombre y el tipo institucional (docente, estudiante o funcionario), que define los roles que la cuenta podrá recibir.</span>
                  </div>
                </div>
              )}
            </>
          )}

          {paso === 2 && sisData && (
            <div className="flex flex-col gap-5">
              <div className="flex items-center gap-2 text-[#0F5C2C] text-[14px]">
                <CheckCircle className="w-[18px] h-[18px]" />
                <span>Código SIS <b className="font-semibold">{codSis}</b> verificado</span>
              </div>

              {Object.keys(formErrors).length > 0 && (
                <div className="flex gap-3 items-start p-3 rounded-[12px] bg-[#FDE2E1] border border-[#F4B7B1] text-[#D92D20]">
                  <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                  <div className="font-semibold text-[14px]">
                    {formErrors.general || `Corrija ${Object.keys(formErrors).length} campos para registrar la cuenta`}
                  </div>
                </div>
              )}

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-[#2C2C2C] flex items-center gap-1">Nombre <span className="text-[#D92D20]">*</span></label>
                  <div className="flex items-center gap-2 h-10 px-3 rounded-[8px] border border-[#C7D2D3] bg-[#F3F8F8] text-[#4F5B62] select-none">
                    <Lock className="w-4 h-4 text-[#8A969B]" />
                    <span className="flex-1 truncate text-[14px]">{sisData.nombre}</span>
                  </div>
                  <div className="text-[13px] text-[#6C757D]">Según el SIS.</div>
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-[#2C2C2C] flex items-center gap-1">Apellido paterno <span className="text-[#D92D20]">*</span></label>
                  <div className="flex items-center gap-2 h-10 px-3 rounded-[8px] border border-[#C7D2D3] bg-[#F3F8F8] text-[#4F5B62] select-none">
                    <Lock className="w-4 h-4 text-[#8A969B]" />
                    <span className="flex-1 truncate text-[14px]">{sisData.paterno}</span>
                  </div>
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-[#2C2C2C] flex items-center gap-1">Apellido materno</label>
                  <div className="flex items-center gap-2 h-10 px-3 rounded-[8px] border border-[#C7D2D3] bg-[#F3F8F8] text-[#4F5B62] select-none">
                    <Lock className="w-4 h-4 text-[#8A969B]" />
                    <span className="flex-1 truncate text-[14px]">{sisData.materno}</span>
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-[#2C2C2C] flex items-center gap-1">Correo institucional <span className="text-[#D92D20]">*</span></label>
                  <div className={`flex items-center gap-2 h-10 px-3 rounded-[8px] border bg-white transition-all ${formErrors.correo ? 'border-[#D92D20] shadow-[0_0_0_3px_rgba(217,45,32,0.12)] text-[#D92D20]' : 'border-[#C7D2D3] focus-within:border-[#005E68] focus-within:shadow-[0_0_0_2px_#fff,0_0_0_4px_rgba(0,94,104,0.30)]'}`}>
                    <svg className={`w-4 h-4 shrink-0 ${formErrors.correo ? 'text-[#D92D20]' : 'text-[#8A969B]'}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <input 
                      type="email" 
                      value={correo}
                      onChange={(e) => { setCorreo(e.target.value); setFormErrors(prev => ({...prev, correo: ''})); }}
                      className="flex-1 w-full bg-transparent outline-none text-[14px] text-[#2C2C2C]" 
                    />
                  </div>
                  {formErrors.correo ? (
                    <div className="text-[13px] text-[#D92D20] font-medium flex gap-1.5 mt-0.5"><AlertCircle className="w-[18px] h-[18px] shrink-0" /> <span>{formErrors.correo}</span></div>
                  ) : (
                    <div className="text-[13px] text-[#6C757D]">Ahí recibirá sus credenciales de acceso.</div>
                  )}
                </div>
                
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-[#2C2C2C] flex items-center gap-1">Teléfono <span className="text-[#6C757D] font-normal">(opcional)</span></label>
                  <div className={`flex items-center gap-2 h-10 px-3 rounded-[8px] border bg-white transition-all ${formErrors.telefono ? 'border-[#D92D20] shadow-[0_0_0_3px_rgba(217,45,32,0.12)] text-[#D92D20]' : 'border-[#C7D2D3] focus-within:border-[#005E68] focus-within:shadow-[0_0_0_2px_#fff,0_0_0_4px_rgba(0,94,104,0.30)]'}`}>
                    <input 
                      type="text" 
                      value={telefono}
                      onChange={(e) => { setTelefono(e.target.value); setFormErrors(prev => ({...prev, telefono: ''})); }}
                      placeholder="8 dígitos" 
                      className="flex-1 w-full bg-transparent outline-none text-[14px] text-[#2C2C2C] placeholder:text-[#8A969B]" 
                    />
                  </div>
                  {formErrors.telefono && (
                    <div className="text-[13px] text-[#D92D20] font-medium flex gap-1.5 mt-0.5"><AlertCircle className="w-[18px] h-[18px] shrink-0" /> <span>{formErrors.telefono}</span></div>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-[#2C2C2C] flex items-center gap-1">Tipo institucional</label>
                  <div className="flex items-center gap-2 h-10 px-3 rounded-[8px] border border-[#C7D2D3] bg-[#F3F8F8] text-[#4F5B62] select-none">
                    <Lock className="w-4 h-4 text-[#8A969B]" />
                    <span className="flex-1 truncate text-[14px]">{sisData.tipo}</span>
                  </div>
                  <div className="text-[13px] text-[#6C757D]">Define los roles posibles.</div>
                </div>
              </div>

              <div className="mt-2 flex gap-3 p-3 md:py-3 md:px-4 rounded-[10px] bg-[#E4EDF9] border border-[#BDD3EE] text-[#1D4E89]">
                <Info className="w-5 h-5 shrink-0 mt-0.5" />
                <div className="flex flex-col">
                  <span className="font-semibold text-[14px]">La cuenta se crea sin rol</span>
                  <span className="text-[13px] opacity-90 mt-0.5 leading-[19px]">Podrá asignarle un rol al terminar. Hasta entonces no podrá usar SCIEM.</span>
                </div>
              </div>
            </div>
          )}

          {paso === 'success' && (
            <div className="flex flex-col gap-6 items-center text-center py-4 md:py-6">
              <div className="w-[56px] h-[56px] rounded-full bg-[#DFF1E7] text-[#15803D] flex items-center justify-center shrink-0">
                <CheckCircle className="w-7 h-7" />
              </div>
              <div className="flex flex-col gap-1">
                <h2 className="text-[20px] font-bold text-[#2C2C2C]">Cuenta creada</h2>
                <p className="text-[14px] text-[#6C757D]">
                  {sisData?.nombre} {sisData?.paterno} {sisData?.materno} · SIS {codSis} · sin rol
                </p>
              </div>
              
              <div className="w-full flex gap-3 p-4 rounded-[12px] bg-[#FFF4D6] border border-[#F5DC8C] text-[#7A5B00] text-left">
                <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                <div className="flex flex-col">
                  <span className="font-semibold text-[14px]">La cuenta todavía no puede usar SCIEM</span>
                  <span className="text-[13px] opacity-90 mt-0.5 leading-[19px]">
                    Asígnele un rol para habilitar sus funciones. Según el SIS, el rol propuesto es {sisData?.tipo}.
                  </span>
                </div>
              </div>
            </div>
          )}

        </div>

        <div className="mt-auto md:mt-0 p-4 md:px-6 md:py-[14px] border-t border-[#DDDDDD] bg-white md:bg-[#FBFCFC] flex flex-col-reverse md:flex-row gap-3 justify-between items-center shrink-0">
          
          {paso === 1 && (
            <>
              <button onClick={handleCerrar} className="w-full md:w-auto h-10 px-4 flex items-center justify-center rounded-[10px] border border-[#C7D2D3] bg-white text-[#2C2C2C] text-[14px] font-semibold hover:bg-[#F7FAFA] transition-colors">
                Cancelar
              </button>
              <button 
                disabled={!sisData}
                onClick={() => setPaso(2)}
                className={`w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] border text-[14px] font-semibold transition-all ${
                  sisData ? 'bg-[#005E68] text-white border-transparent hover:bg-[#004B53]' : 'bg-[#F3F5F5] text-[#AAB8BA] border-[#DDDDDD] cursor-not-allowed'
                }`}
              >
                Continuar <ArrowRight className="w-4 h-4" />
              </button>
            </>
          )}

          {paso === 2 && (
            <>
              <button onClick={() => { setPaso(1); setFormErrors({}); }} className="w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] border border-[#C7D2D3] bg-white text-[#2C2C2C] text-[14px] font-semibold hover:bg-[#F7FAFA] transition-colors">
                <ChevronLeft className="w-4 h-4" /> Atrás
              </button>
              <div className="flex flex-col-reverse md:flex-row gap-3 w-full md:w-auto">
                <button onClick={handleCerrar} className="w-full md:w-auto h-10 px-4 flex items-center justify-center rounded-[10px] border border-[#C7D2D3] bg-white text-[#2C2C2C] text-[14px] font-semibold hover:bg-[#F7FAFA] transition-colors">
                  Cancelar
                </button>
                <button 
                  onClick={handleRegistrar}
                  disabled={isSubmitting}
                  className="w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] bg-[#005E68] text-white text-[14px] font-semibold hover:bg-[#004B53] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isSubmitting ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Check className="w-4 h-4 stroke-[3]" />} 
                  Registrar cuenta
                </button>
              </div>
            </>
          )}

          {paso === 'success' && (
            <>
              <button 
                onClick={() => { setPaso(1); setCodSis(''); setSisData(null); setCorreo(''); setTelefono(''); }} 
                className="hidden md:flex w-full md:w-auto h-10 px-4 items-center justify-center gap-2 rounded-[10px] text-[#005E68] text-[14px] font-semibold hover:bg-[#F3F8F8] transition-colors"
              >
                Registrar otra
              </button>
              <div className="flex flex-col-reverse md:flex-row gap-3 w-full md:w-auto">
                <button 
                  onClick={() => { 
                    handleCerrar(); 
                    if(onSuccess) onSuccess(sisData); 
                  }} 
                  className="w-full md:w-auto h-10 px-4 flex items-center justify-center rounded-[10px] border border-[#C7D2D3] bg-white text-[#2C2C2C] text-[14px] font-semibold hover:bg-[#F7FAFA] transition-colors"
                >
                  Cerrar
                </button>
                <button 
                  onClick={() => { 
                    handleCerrar(); 
                    if(onSuccess) onSuccess(sisData); 
                  }} 
                  className="w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] bg-[#005E68] text-white text-[14px] font-semibold hover:bg-[#004B53] transition-colors"
                >
                  <CheckCircle className="w-4 h-4" /> Asignar rol ahora
                </button>
              </div>
            </>
          )}

        </div>
      </div>
    </div>
  );
};