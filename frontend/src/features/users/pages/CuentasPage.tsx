import React, { useState } from 'react';
import { CheckCircle, X } from 'lucide-react';
import { AppShell } from '@/components/layout/AppShell';
import { PageHeader } from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { RegistrarCuentaModal } from '../components/RegistrarCuentaModal';

export const CuentasPage: React.FC = () => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [toastData, setToastData] = useState<any>(null);

  const handleRegistroExitoso = (userData: any) => {
    setToastData(userData);
    setTimeout(() => setToastData(null), 5000);
  };

  return (
    <AppShell
      mobileTitle="Cuentas"
      breadcrumbs={[{ label: 'Administración' }, { label: 'Cuentas' }]}
    >
      {/* TOAST DE ÉXITO — fijo a la ventana, no al contenido, para que no se desplace al hacer scroll */}
      {toastData && (
        <div className="fixed right-4 top-20 z-40 flex gap-3 items-start w-[380px] max-w-[calc(100vw-2rem)] p-3.5 bg-surface border border-border-soft border-l-4 border-l-ok rounded-[10px] shadow-[var(--shadow-lg)] animate-in fade-in slide-in-from-top-4 duration-300">
          <CheckCircle className="w-5 h-5 text-ok shrink-0 mt-0.5" />
          <div className="grow flex flex-col">
            <span className="font-semibold text-[14px] text-text">Cuenta creada</span>
            <span className="text-[13px] text-muted-foreground leading-[18px] mt-0.5">
              {toastData.nombre} {toastData.paterno} quedó sin rol. Asígnele uno para que pueda ingresar.
            </span>
          </div>
          <button onClick={() => setToastData(null)} className="text-subtle hover:text-muted-foreground shrink-0">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      <PageHeader
        title="Cuentas"
        subtitle="Personas con acceso a SCIEM. El tipo institucional viene del SIS."
        actions={
          <Button onClick={() => setIsModalOpen(true)}>Registrar cuenta</Button>
        }
      />

      <Card className="p-8 text-center text-muted-foreground">
        Aquí irá la tabla de cuentas (HU-002) más adelante.
      </Card>

      <RegistrarCuentaModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSuccess={handleRegistroExitoso}
      />
    </AppShell>
  );
};
