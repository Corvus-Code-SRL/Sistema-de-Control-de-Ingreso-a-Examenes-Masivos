import { useEffect, useState } from 'react';
import { SubjectCareer, Group } from '../types/subject';
import { fetchSubjectCareers, fetchGroupsForPair } from '../services/subjectService';
import { useSubject } from '../context/SubjectContext';

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

  if (loading) return <p>Cargando materias...</p>;
  if (error) return <p className="text-red-600">{error}</p>;
  if (emptyMessage) return <p>{emptyMessage}</p>;

  const myPairs = pairs.filter((p) => p.es_mia);
  const catalogPairs = pairs.filter((p) => !p.es_mia);

  function renderPair(pair: SubjectCareer) {
    const isSelected =
      selectedSubject?.id_materia === pair.id_materia &&
      selectedSubject?.id_carrera === pair.id_carrera;

    return (
      <li key={`${pair.id_materia}-${pair.id_carrera}`}>
        <button
          disabled={!pair.activa}
          onClick={() => handleSelect(pair)}
          className={`w-full text-left p-3 rounded border ${
            !pair.activa
              ? 'opacity-50 cursor-not-allowed border-gray-200'
              : isSelected
              ? 'border-blue-500 bg-blue-50'
              : 'border-gray-300 hover:bg-gray-50'
          }`}
        >
          {pair.codigo} — {pair.nombre}{' '}
          <span className="text-sm text-gray-500">({pair.carrera.nombre})</span>
          {!pair.activa && <span className="ml-2 text-xs text-red-500">Inactiva</span>}
        </button>
      </li>
    );
  }

  return (
    <div className="p-4">
      <h2 className="text-lg font-semibold mb-2">Mis materias</h2>
      <ul className="space-y-2 mb-6">
        {myPairs.length === 0 && (
          <p className="text-sm text-gray-500">No tienes materias asignadas este periodo.</p>
        )}
        {myPairs.map(renderPair)}
      </ul>

      <h2 className="text-lg font-semibold mb-2">Catálogo institucional</h2>
      <ul className="space-y-2">{catalogPairs.map(renderPair)}</ul>

      {selectedSubject && (
        <div className="mt-6 border-t pt-4">
          <h3 className="font-medium mb-2">
            Grupos de {selectedSubject.nombre} — {selectedSubject.carrera.nombre}
          </h3>
          {groupAlert && (
            <div className="mb-3 p-2 bg-yellow-50 border border-yellow-300 text-yellow-800 rounded text-sm">
              {groupAlert}
            </div>
          )}
          <ul className="list-disc pl-5">
            {groups.map((g) => (
              <li key={g.id_grupo}>
                Grupo {g.num_grupo} — {g.periodo.nombre_periodo} {g.es_mio && '(mío)'}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}