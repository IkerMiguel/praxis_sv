<?php

namespace App\Services;

use App\Enums\EtapaEvidencia;
use App\Exceptions\ReglaNegocioException;
use App\Models\Evidencia;
use App\Models\Incidencia;
use App\Models\User;
use App\Support\Parametro;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Carga de evidencias con validación del tipo real del archivo por sus magic bytes (HU-3.2),
 * almacenamiento en disco configurable (local / S3 / MinIO) y conservación permanente (RN-042).
 */
class EvidenciaService
{
    public const MENSAJE_FORMATO = 'Formato no permitido. Solo se aceptan imágenes JPG, PNG, WEBP o documentos PDF';

    public function __construct(private ParametrosService $parametros) {}

    /**
     * Detecta el tipo real del archivo a partir de su cabecera binaria.
     *
     * @return array{mime: string, extension: string}|null
     */
    public function detectarTipo(string $ruta): ?array
    {
        $manejador = @fopen($ruta, 'rb');

        if ($manejador === false) {
            return null;
        }

        $cabecera = (string) fread($manejador, 16);
        fclose($manejador);

        return match (true) {
            str_starts_with($cabecera, "\xFF\xD8\xFF") => ['mime' => 'image/jpeg', 'extension' => 'jpg'],
            str_starts_with($cabecera, "\x89PNG\r\n\x1A\n") => ['mime' => 'image/png', 'extension' => 'png'],
            str_starts_with($cabecera, 'RIFF') && substr($cabecera, 8, 4) === 'WEBP' => ['mime' => 'image/webp', 'extension' => 'webp'],
            str_starts_with($cabecera, '%PDF-') => ['mime' => 'application/pdf', 'extension' => 'pdf'],
            default => null,
        };
    }

    /**
     * @param  list<UploadedFile>  $archivos
     * @return Collection<int, Evidencia>
     */
    public function cargar(array $archivos, User $usuario, EtapaEvidencia $etapa, ?Incidencia $incidencia = null): Collection
    {
        $maximoArchivos = $this->parametros->entero(Parametro::MAX_EVIDENCIAS_POR_REPORTE);
        $maximoMb = $this->parametros->entero(Parametro::TAMANO_MAXIMO_EVIDENCIA_MB);

        if ($incidencia !== null && $incidencia->estado_codigo->esTerminal()) {
            throw ReglaNegocioException::conflicto('La incidencia está finalizada y su expediente no admite nuevas evidencias (RN-042).', 'RN-042');
        }

        if ($incidencia !== null && $etapa === EtapaEvidencia::Reporte) {
            $existentes = $incidencia->evidencias()->where('etapa', EtapaEvidencia::Reporte->value)->count();

            if ($existentes + count($archivos) > $maximoArchivos) {
                throw ValidationException::withMessages([
                    'archivos' => "Se ha alcanzado el límite máximo permitido de {$maximoArchivos} archivos por reporte",
                ]);
            }
        }

        $tipos = [];

        foreach ($archivos as $indice => $archivo) {
            if ($archivo->getSize() > $maximoMb * 1024 * 1024) {
                throw ValidationException::withMessages([
                    "archivos.{$indice}" => "El archivo supera el tamaño máximo autorizado de {$maximoMb} MB",
                ]);
            }

            $tipo = $this->detectarTipo($archivo->getRealPath());

            if ($tipo === null) {
                throw ValidationException::withMessages(["archivos.{$indice}" => self::MENSAJE_FORMATO]);
            }

            $tipos[$indice] = $tipo;
        }

        $disco = (string) config('praxis.disco_evidencias');
        $carpeta = $incidencia !== null ? 'incidencias/'.$incidencia->id : 'temporales/'.$usuario->id;

        return DB::transaction(fn () => collect($archivos)->map(function (UploadedFile $archivo, int $indice) use ($tipos, $disco, $carpeta, $usuario, $etapa, $incidencia): Evidencia {
            $nombre = Str::uuid().'.'.$tipos[$indice]['extension'];
            $ruta = Storage::disk($disco)->putFileAs($carpeta, $archivo, $nombre);

            return Evidencia::create([
                'incidencia_id' => $incidencia?->id,
                'etapa' => $etapa->value,
                'nombre_original' => Str::limit($archivo->getClientOriginalName(), 250, ''),
                'disco' => $disco,
                'ruta_storage' => $ruta,
                'mime_type' => $tipos[$indice]['mime'],
                'tamano_bytes' => (int) $archivo->getSize(),
                'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
                'usuario_carga_id' => $usuario->id,
                'created_at' => now(),
            ]);
        }));
    }

    /**
     * Vincula evidencias cargadas previamente (sin incidencia) por el mismo usuario.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Evidencia>
     */
    public function vincularPendientes(array $ids, User $usuario, Incidencia $incidencia, EtapaEvidencia $etapa, ?string $referenciaTipo = null, ?int $referenciaId = null): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $evidencias = Evidencia::query()
            ->whereIn('id', $ids)
            ->where('usuario_carga_id', $usuario->id)
            ->whereNull('incidencia_id')
            ->lockForUpdate()
            ->get();

        if ($evidencias->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'evidencias' => 'Alguna de las evidencias no existe, no le pertenece o ya fue vinculada a otra incidencia.',
            ]);
        }

        Evidencia::query()->whereIn('id', $evidencias->pluck('id'))->update([
            'incidencia_id' => $incidencia->id,
            'etapa' => $etapa->value,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
        ]);

        return Evidencia::query()->whereIn('id', $evidencias->pluck('id'))->get();
    }

    /**
     * Referencias de evidencias para el evento de auditoría (hash SHA-256 y metadatos, D10).
     *
     * @param  Collection<int, Evidencia>  $evidencias
     * @return list<array<string, mixed>>|null
     */
    public function paraAuditoria(Collection $evidencias): ?array
    {
        if ($evidencias->isEmpty()) {
            return null;
        }

        return $evidencias->map(fn (Evidencia $evidencia) => [
            'evidencia_id' => $evidencia->id,
            'sha256' => $evidencia->hash_sha256,
            'uri' => $evidencia->disco.'://'.$evidencia->ruta_storage,
            'mime_type' => $evidencia->mime_type,
            'nombre_original' => $evidencia->nombre_original,
        ])->values()->all();
    }
}
