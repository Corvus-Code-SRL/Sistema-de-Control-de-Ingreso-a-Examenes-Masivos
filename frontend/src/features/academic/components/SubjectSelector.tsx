import { useEffect, useState } from 'react';
import { SubjectCareer, Group } from '../types/subject';
import { fetchSubjectCareers, fetchGroupsForPair } from '../services/subjectService';
import { useSubject } from '../context/SubjectContext';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

export default function SubjectSelector() {
  const [pairs, setPairs] = useState<SubjectCareer[]>([]);
  const [groups, setGroups] = useState<Group[]>([]);
  const [emptyMessage, setEmptyMessage] = useState<string | null>(null);
  const [groupAlert, setGroupAlert] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const { selectedSubject, selectSubject } = useSubject();

  useEffect(() => {
    fetchSubjectCareers()
      .then((res) => {
        setPairs(res.data);
        setEmptyMessage(res.mensaje ?? null);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  async function handleSelect(pair: SubjectCareer) {
    if (!pair.activa) return; // criterio: materia inactiva no seleccionable
    setError(null);
    setGroupAlert(null);
    selectSubject(pair);

    try {
      const res = await fetchGroupsForPair(pair.id_carrera, pair.id_materia);
      setGroups(res.data.grupos);
      if (res.meta.total_mios === 0) {
        setGroupAlert('No tienes grupos asignados en esta materia/carrera.');
      }
    } catch (err) {
      setError((err as Error).message);
    }
  }

  if (loading) return <p className="p-4 text-sm text-muted-foreground">Cargando materias...</p>;
  if (error) return <p className="p-4 text-sm text-destructive">{error}</p>;
  if (emptyMessage) return <p className="p-4 text-sm text-muted-foreground">{emptyMessage}</p>;

  const myPairs = pairs.filter((p) => p.es_mia);
  const catalogPairs = pairs.filter((p) => !p.es_mia);

  function renderPair(pair: SubjectCareer) {
    const isSelected =
      selectedSubject?.id_materia === pair.id_materia &&
      selectedSubject?.id_carrera === pair.id_carrera;

    return (
      <Card
        key={`${pair.id_materia}-${pair.id_carrera}`}
        onClick={() => handleSelect(pair)}
        className={[
          'transition-colors',
          !pair.activa
            ? 'cursor-not-allowed opacity-50'
            : 'cursor-pointer hover:bg-muted',
          isSelected ? 'ring-2 ring-primary' : '',
        ].join(' ')}
      >
        <CardContent className="flex items-center justify-between gap-2">
          <div>
            <span className="font-medium">
              {pair.codigo} — {pair.nombre}
            </span>{' '}
            <span className="text-sm text-muted-foreground">
              ({pair.carrera.nombre})
            </span>
          </div>
          <div className="flex items-center gap-1.5">
            {!pair.activa && <Badge variant="destructive">Inactiva</Badge>}
            {pair.es_mia && <Badge variant="secondary">Mía</Badge>}
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6 p-4">
      <div>
        <h2 className="mb-2 text-lg font-semibold">Mis materias</h2>
        <div className="space-y-2">
          {myPairs.length === 0 && (
            <p className="text-sm text-muted-foreground">
              No tienes materias asignadas este periodo.
            </p>
          )}
          {myPairs.map(renderPair)}
        </div>
      </div>

      <div>
        <h2 className="mb-2 text-lg font-semibold">Catálogo institucional</h2>
        <div className="space-y-2">{catalogPairs.map(renderPair)}</div>
      </div>

      {selectedSubject && (
        <Card>
          <CardContent className="space-y-3">
            <h3 className="font-medium">
              Grupos de {selectedSubject.nombre} — {selectedSubject.carrera.nombre}
            </h3>

            {groupAlert && (
              <div className="rounded-xl border border-border bg-muted p-3 text-sm text-foreground">
                {groupAlert}
              </div>
            )}

            <ul className="space-y-1">
              {groups.map((g) => (
                <li key={g.id_grupo} className="flex items-center gap-2 text-sm">
                  Grupo {g.num_grupo} — {g.periodo.nombre_periodo}
                  {g.es_mio && <Badge variant="secondary">mío</Badge>}
                </li>
              ))}
              {groups.length === 0 && (
                <li className="text-sm text-muted-foreground">
                  No hay grupos registrados para esta materia.
                </li>
              )}
            </ul>
          </CardContent>
        </Card>
      )}
    </div>
  );
}