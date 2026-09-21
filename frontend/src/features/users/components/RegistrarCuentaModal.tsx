import React, { useState } from 'react';
import {
  Search, Info, X, ChevronLeft, ArrowRight, Pencil,
  RefreshCw, CheckCircle, GraduationCap, AlertCircle, Lock, Check, Trash2
} from 'lucide-react';
import { env } from '@/config/env';

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
      const response = await fetch(`${env.apiUrl}/sis/verificar/${codSis}`, {
        headers: { 'Accept': 'application/json' }
      });

      if (response.status === 503) {
        setErrorType('sis_down');
        setIsVerifying(false);
        return;
      }

      const data = await response.json();

      if (response.status === 422) {
        const mensajeError = data.errors?.cod_sis?.[0] || data.message || '';
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
        return;
      }

      // Cualquier otra respuesta del servidor deja la verificación sin resolver:
      // se muestra el mismo aviso que un fallo de red en vez de fallar en silencio.
      setErrorType('sis_down');
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
      const response = await fetch(`${env.apiUrl}/usuarios`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        // Los nombres de campo son los que declara StoreUserRequest.
        body: JSON.stringify({
          cod_sis: codSis,
          nombre: sisData.nombre,
          apellido_paterno: sisData.paterno,
          apellido_materno: sisData.materno,
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
    <div className="fixed inset-0 z-50 flex flex-col md:items-center md:justify-center bg-surface md:bg-[var(--sciem-scrim)] md:p-4">
      <div className="flex flex-col bg-surface w-full h-full md:h-auto md:max-h-[90vh] md:w-[640px] md:rounded-[14px] md:shadow-[var(--shadow-lg)] overflow-hidden relative">
        
        {showCancelConfirm && (
          <div className="absolute inset-0 z-50 bg-[var(--sciem-scrim)] backdrop-blur-[2px] flex items-center justify-center p-4">
            <div className="bg-surface rounded-[14px] shadow-[var(--shadow-lg)] w-full max-w-[440px] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
              <div className="flex items-center gap-3 px-6 pt-5 pb-4">
                <span className="w-10 h-10 rounded-full bg-danger-soft text-danger flex items-center justify-center shrink-0">
                  <Trash2 className="w-5 h-5" />
                </span>
                <h3 className="text-[18px] font-semibold text-text">¿Descartar el registro?</h3>
              </div>
              <div className="px-6 pb-6 text-[14px] text-muted-foreground leading-[20px]">
                No se creará la cuenta de <b className="font-semibold text-text">{sisData?.nombre} {sisData?.paterno} {sisData?.materno}</b> y se perderán los datos ingresados.
              </div>
              <div className="p-4 border-t border-border-soft bg-sunken flex flex-col-reverse md:flex-row justify-end gap-3">
                <button onClick={() => setShowCancelConfirm(false)} className="h-10 px-4 w-full md:w-auto rounded-[10px] border border-border-strong bg-surface text-text font-semibold text-[14px] hover:bg-sunken transition-colors">
                  Seguir registrando
                </button>
                <button onClick={cerrarPorCompleto} className="h-10 px-4 w-full md:w-auto rounded-[10px] bg-danger text-primary-foreground font-semibold text-[14px] hover:bg-danger-fg transition-colors">
                  Descartar
                </button>
              </div>
            </div>
          </div>
        )}

        <div className="md:hidden flex items-center gap-2 h-14 px-2 border-b border-border-soft bg-surface shrink-0">
          <button onClick={handleCerrar} className="w-10 h-10 flex items-center justify-center text-muted-foreground rounded-[8px]">
            <ChevronLeft className="w-5 h-5" />
          </button>
          <div className="flex-1 font-semibold text-[16px] text-text truncate">Registrar cuenta</div>
        </div>

        <div className="hidden md:flex items-start justify-between px-6 pt-5 pb-1 shrink-0">
          <div className="flex-1 min-w-0">
            <h2 className="text-[18px] leading-[26px] font-semibold text-text">Registrar cuenta</h2>
            <p className="text-[13px] text-muted-foreground leading-[19px] mt-0.5">
              {paso === 1 ? 'Paso 1 de 2' : paso === 2 ? 'Paso 2 de 2' : 'Registrada a las 09:14 · queda en la bitácora'}
            </p>
          </div>
          <button onClick={handleCerrar} className="w-8 h-8 flex items-center justify-center text-muted-foreground hover:bg-bg-app rounded-[8px] transition-colors">
            <X className="w-4 h-4" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 md:px-6 md:py-4 flex flex-col gap-4">
          
          {paso !== 'success' && (
            <div className="hidden md:flex items-center gap-2 mb-2">
              <span className={`flex items-center gap-2 text-[13px] ${paso === 1 ? 'font-semibold text-text' : 'font-semibold text-brand'}`}>
                <span className={`w-6 h-6 rounded-full flex items-center justify-center text-[12px] ${paso === 1 ? 'bg-brand text-primary-foreground' : 'bg-brand-soft text-brand'}`}>
                  {paso === 1 ? '1' : <Check className="w-3.5 h-3.5 stroke-[3]" />}
                </span>
                <span>Código SIS</span>
              </span>
              <span className="flex-1 h-[1px] bg-border-strong min-w-[16px]"></span>
              <span className={`flex items-center gap-2 text-[13px] ${paso === 2 ? 'font-semibold text-text' : 'font-medium text-muted-foreground'}`}>
                <span className={`w-6 h-6 rounded-full border-[1.5px] flex items-center justify-center text-[12px] font-bold ${paso === 2 ? 'bg-brand border-brand text-primary-foreground' : 'border-border-strong bg-surface'}`}>
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
                  <label className="text-[14px] font-medium text-text flex items-center gap-1">
                    Código SIS <span className="text-danger">*</span>
                  </label>
                  
                  <div className={`flex items-center gap-2 h-10 px-3 rounded-[8px] border bg-surface transition-all ${
                    hasInputError
                      ? 'border-danger shadow-[0_0_0_3px_rgba(217,45,32,0.12)] text-danger'
                      : sisData || isVerifying 
                        ? 'border-border-strong' 
                        : 'border-border-strong focus-within:border-brand focus-within:shadow-[var(--focus-ring)]'
                  }`}>
                    <span className={`${hasInputError ? 'text-danger' : 'text-subtle'} font-medium select-none`}>#</span>
                    <input 
                      type="text" 
                      value={codSis}
                      onChange={(e) => {
                        setCodSis(e.target.value);
                        setErrorType(null); 
                      }}
                      placeholder="9 dígitos"
                      disabled={isVerifying || !!sisData}
                      className="flex-1 w-full bg-transparent outline-none text-[14px] placeholder:text-subtle disabled:bg-transparent"
                      maxLength={9}
                    />
                  </div>
                  
                  {errorType === 'not_found' && (
                    <div className="text-[13px] text-danger font-medium flex gap-1.5 mt-0.5">
                      <AlertCircle className="w-[18px] h-[18px] shrink-0" />
                      <span>{backendErrorMsg || 'No se encontró a ninguna persona con este código en el SIS. Revise el número.'}</span>
                    </div>
                  )}

                  {errorType === 'duplicate' && (
                    <div className="flex flex-col gap-1 mt-0.5">
                      <div className="text-[13px] text-danger font-medium flex gap-1.5">
                        <AlertCircle className="w-[18px] h-[18px] shrink-0" />
                        <span>{backendErrorMsg}</span>
                      </div>
                      <button className="text-[13px] font-semibold text-brand text-left hover:underline w-max">
                        Ver la cuenta existente
                      </button>
                    </div>
                  )}

                  {!hasInputError && (
                    <div className="text-[13px] text-muted-foreground leading-[18px]">
                      {sisData ? 'Verificado a las 09:14.' : 'Se consulta en la fuente institucional antes de continuar.'}
                    </div>
                  )}
                </div>

                {sisData ? (
                  <button onClick={() => { setSisData(null); setCodSis(''); }} className="mt-7 w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] text-brand text-[14px] font-semibold hover:bg-bg-app transition-colors shrink-0">
                    <Pencil className="w-4 h-4" /> Cambiar
                  </button>
                ) : (
                  <button onClick={handleVerificarSIS} disabled={isVerifying || codSis.trim() === ''} className="mt-7 w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] border border-border-strong bg-surface text-text text-[14px] font-semibold hover:bg-sunken transition-colors shrink-0 disabled:opacity-50 disabled:cursor-not-allowed">
                    {isVerifying ? <><RefreshCw className="w-4 h-4 animate-spin text-subtle" /> <span className="text-subtle">Verificando...</span></> : <><Search className="w-4 h-4" /> Verificar en el SIS</>}
                  </button>
                )}
              </div>

              {errorType === 'sis_down' && (
                <div className="mt-2 flex gap-3 p-4 rounded-[12px] bg-danger-soft border border-[#F4B7B1] text-danger">
                  <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                  <div className="flex flex-col gap-3 w-full">
                    <div className="flex flex-col">
                      <span className="font-semibold text-[14px]">El SIS no está disponible</span>
                      <span className="text-[13px] opacity-90 mt-0.5 leading-[19px]">No se puede validar a la persona en este momento, así que el registro no puede continuar. Intente de nuevo en unos minutos.</span>
                    </div>
                    <button onClick={handleVerificarSIS} className="w-max h-8 px-3 flex items-center justify-center gap-2 rounded-[8px] border border-border-strong bg-surface text-text text-[13px] font-semibold hover:bg-sunken transition-colors">
                      <RefreshCw className="w-3.5 h-3.5" /> Reintentar
                    </button>
                  </div>
                </div>
              )}

              {isVerifying && (
                <div className="mt-2 flex flex-col gap-2 p-4 rounded-[12px] border border-border-soft bg-sunken">
                  <span className="text-[13px] text-muted-foreground">Consultando la fuente institucional...</span>
                  <div className="h-1.5 bg-brand-soft rounded-full overflow-hidden">
                    <div className="h-full bg-brand rounded-full w-[55%] animate-pulse"></div>
                  </div>
                </div>
              )}

              {sisData && (
                <div className="mt-2 flex flex-col gap-3 p-4 rounded-[12px] border border-[#B6DEC6] bg-ok-soft">
                  <div className="flex items-center gap-2 text-ok-fg">
                    <CheckCircle className="w-[18px] h-[18px]" />
                    <span className="font-semibold text-[14px]">Persona reconocida por el SIS</span>
                  </div>
                  <div className="flex items-center gap-3.5">
                    <div className="w-14 h-14 rounded-full bg-brand-soft text-brand-deep flex items-center justify-center font-bold text-[18px] shrink-0">
                      {sisData.nombre.charAt(0)}{sisData.paterno.charAt(0)}
                    </div>
                    <div className="flex flex-col gap-1">
                      <span className="font-semibold text-[18px] leading-[28px] text-text">
                        {sisData.nombre} {sisData.paterno} {sisData.materno}
                      </span>
                      <div className="flex flex-wrap items-center gap-2 mt-0.5">
                        <span className="px-2.5 py-0.5 rounded-full bg-info-soft text-info text-[12px] font-semibold flex items-center gap-1.5 border border-info-border">
                          <GraduationCap className="w-3.5 h-3.5" />
                          Tipo institucional: {sisData.tipo}
                        </span>
                        <span className="text-[13px] text-muted-foreground leading-tight max-w-[120px] md:max-w-none">{sisData.facultad}</span>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {!isVerifying && !sisData && !errorType && (
                <div className="mt-2 flex gap-3 p-3 md:py-3 md:px-4 rounded-[10px] bg-info-soft border border-info-border text-info">
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
              <div className="flex items-center gap-2 text-ok-fg text-[14px]">
                <CheckCircle className="w-[18px] h-[18px]" />
                <span>Código SIS <b className="font-semibold">{codSis}</b> verificado</span>
              </div>

              {Object.keys(formErrors).length > 0 && (
                <div className="flex gap-3 items-start p-3 rounded-[12px] bg-danger-soft border border-[#F4B7B1] text-danger">
                  <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                  <div className="font-semibold text-[14px]">
                    {formErrors.general || `Corrija ${Object.keys(formErrors).length} campos para registrar la cuenta`}
                  </div>
                </div>
              )}

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-text flex items-center gap-1">Nombre <span className="text-danger">*</span></label>
                  <div className="flex items-center gap-2 h-10 px-3 rounded-[8px] border border-border-strong bg-bg-app text-muted-foreground select-none">
                    <Lock className="w-4 h-4 text-subtle" />
                    <span className="flex-1 truncate text-[14px]">{sisData.nombre}</span>
                  </div>
                  <div className="text-[13px] text-muted-foreground">Según el SIS.</div>
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-text flex items-center gap-1">Apellido paterno <span className="text-danger">*</span></label>
                  <div className="flex items-center gap-2 h-10 px-3 rounded-[8px] border border-border-strong bg-bg-app text-muted-foreground select-none">
                    <Lock className="w-4 h-4 text-subtle" />
                    <span className="flex-1 truncate text-[14px]">{sisData.paterno}</span>
                  </div>
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-text flex items-center gap-1">Apellido materno</label>
                  <div className="flex items-center gap-2 h-10 px-3 rounded-[8px] border border-border-strong bg-bg-app text-muted-foreground select-none">
                    <Lock className="w-4 h-4 text-subtle" />
                    <span className="flex-1 truncate text-[14px]">{sisData.materno}</span>
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-text flex items-center gap-1">Correo institucional <span className="text-danger">*</span></label>
                  <div className={`flex items-center gap-2 h-10 px-3 rounded-[8px] border bg-surface transition-all ${formErrors.correo ? 'border-danger shadow-[0_0_0_3px_rgba(217,45,32,0.12)] text-danger' : 'border-border-strong focus-within:border-brand focus-within:shadow-[var(--focus-ring)]'}`}>
                    <svg className={`w-4 h-4 shrink-0 ${formErrors.correo ? 'text-danger' : 'text-subtle'}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <input 
                      type="email" 
                      value={correo}
                      onChange={(e) => { setCorreo(e.target.value); setFormErrors(prev => ({...prev, correo: ''})); }}
                      className="flex-1 w-full bg-transparent outline-none text-[14px] text-text" 
                    />
                  </div>
                  {formErrors.correo ? (
                    <div className="text-[13px] text-danger font-medium flex gap-1.5 mt-0.5"><AlertCircle className="w-[18px] h-[18px] shrink-0" /> <span>{formErrors.correo}</span></div>
                  ) : (
                    <div className="text-[13px] text-muted-foreground">Ahí recibirá sus credenciales de acceso.</div>
                  )}
                </div>
                
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-text flex items-center gap-1">Teléfono <span className="text-muted-foreground font-normal">(opcional)</span></label>
                  <div className={`flex items-center gap-2 h-10 px-3 rounded-[8px] border bg-surface transition-all ${formErrors.telefono ? 'border-danger shadow-[0_0_0_3px_rgba(217,45,32,0.12)] text-danger' : 'border-border-strong focus-within:border-brand focus-within:shadow-[var(--focus-ring)]'}`}>
                    <input 
                      type="text" 
                      value={telefono}
                      onChange={(e) => { setTelefono(e.target.value); setFormErrors(prev => ({...prev, telefono: ''})); }}
                      placeholder="8 dígitos" 
                      className="flex-1 w-full bg-transparent outline-none text-[14px] text-text placeholder:text-subtle" 
                    />
                  </div>
                  {formErrors.telefono && (
                    <div className="text-[13px] text-danger font-medium flex gap-1.5 mt-0.5"><AlertCircle className="w-[18px] h-[18px] shrink-0" /> <span>{formErrors.telefono}</span></div>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-[14px] font-medium text-text flex items-center gap-1">Tipo institucional</label>
                  <div className="flex items-center gap-2 h-10 px-3 rounded-[8px] border border-border-strong bg-bg-app text-muted-foreground select-none">
                    <Lock className="w-4 h-4 text-subtle" />
                    <span className="flex-1 truncate text-[14px]">{sisData.tipo}</span>
                  </div>
                  <div className="text-[13px] text-muted-foreground">Define los roles posibles.</div>
                </div>
              </div>

              <div className="mt-2 flex gap-3 p-3 md:py-3 md:px-4 rounded-[10px] bg-info-soft border border-info-border text-info">
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
              <div className="w-[56px] h-[56px] rounded-full bg-ok-soft text-ok flex items-center justify-center shrink-0">
                <CheckCircle className="w-7 h-7" />
              </div>
              <div className="flex flex-col gap-1">
                <h2 className="text-[20px] font-bold text-text">Cuenta creada</h2>
                <p className="text-[14px] text-muted-foreground">
                  {sisData?.nombre} {sisData?.paterno} {sisData?.materno} · SIS {codSis} · sin rol
                </p>
              </div>
              
              <div className="w-full flex gap-3 p-4 rounded-[12px] bg-warn-soft border border-warn-border text-warn-fg text-left">
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

        <div className="mt-auto md:mt-0 p-4 md:px-6 md:py-[14px] border-t border-border-soft bg-surface md:bg-sunken flex flex-col-reverse md:flex-row gap-3 justify-between items-center shrink-0">
          
          {paso === 1 && (
            <>
              <button onClick={handleCerrar} className="w-full md:w-auto h-10 px-4 flex items-center justify-center rounded-[10px] border border-border-strong bg-surface text-text text-[14px] font-semibold hover:bg-sunken transition-colors">
                Cancelar
              </button>
              <button 
                disabled={!sisData}
                onClick={() => setPaso(2)}
                className={`w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] border text-[14px] font-semibold transition-all ${
                  sisData ? 'bg-brand text-primary-foreground border-transparent hover:bg-brand-active' : 'bg-bg-app text-dis-text border-border-soft cursor-not-allowed'
                }`}
              >
                Continuar <ArrowRight className="w-4 h-4" />
              </button>
            </>
          )}

          {paso === 2 && (
            <>
              <button onClick={() => { setPaso(1); setFormErrors({}); }} className="w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] border border-border-strong bg-surface text-text text-[14px] font-semibold hover:bg-sunken transition-colors">
                <ChevronLeft className="w-4 h-4" /> Atrás
              </button>
              <div className="flex flex-col-reverse md:flex-row gap-3 w-full md:w-auto">
                <button onClick={handleCerrar} className="w-full md:w-auto h-10 px-4 flex items-center justify-center rounded-[10px] border border-border-strong bg-surface text-text text-[14px] font-semibold hover:bg-sunken transition-colors">
                  Cancelar
                </button>
                <button 
                  onClick={handleRegistrar}
                  disabled={isSubmitting}
                  className="w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] bg-brand text-primary-foreground text-[14px] font-semibold hover:bg-brand-active transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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
                className="hidden md:flex w-full md:w-auto h-10 px-4 items-center justify-center gap-2 rounded-[10px] text-brand text-[14px] font-semibold hover:bg-bg-app transition-colors"
              >
                Registrar otra
              </button>
              <div className="flex flex-col-reverse md:flex-row gap-3 w-full md:w-auto">
                <button 
                  onClick={() => { 
                    handleCerrar(); 
                    if(onSuccess) onSuccess(sisData); 
                  }} 
                  className="w-full md:w-auto h-10 px-4 flex items-center justify-center rounded-[10px] border border-border-strong bg-surface text-text text-[14px] font-semibold hover:bg-sunken transition-colors"
                >
                  Cerrar
                </button>
                <button 
                  onClick={() => { 
                    handleCerrar(); 
                    if(onSuccess) onSuccess(sisData); 
                  }} 
                  className="w-full md:w-auto h-10 px-4 flex items-center justify-center gap-2 rounded-[10px] bg-brand text-primary-foreground text-[14px] font-semibold hover:bg-brand-active transition-colors"
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