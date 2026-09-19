import { createContext, useContext, useState, ReactNode } from 'react';
import { SubjectCareer } from '../types/subject';

interface SubjectContextType {
  selectedSubject: SubjectCareer | null;
  selectSubject: (subject: SubjectCareer) => void;
}

const SubjectContext = createContext<SubjectContextType | undefined>(undefined);

export function SubjectProvider({ children }: { children: ReactNode }) {
  const [selectedSubject, setSelectedSubject] = useState<SubjectCareer | null>(null);

  return (
    <SubjectContext.Provider value={{ selectedSubject, selectSubject: setSelectedSubject }}>
      {children}
    </SubjectContext.Provider>
  );
}

export function useSubject() {
  const context = useContext(SubjectContext);
  if (!context) throw new Error('useSubject debe usarse dentro de SubjectProvider');
  return context;
}