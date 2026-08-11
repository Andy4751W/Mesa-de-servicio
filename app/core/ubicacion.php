<?php
declare(strict_types=1);

/**
 * Jerarquía territorial de la Mesa de Servicio.
 *
 * El contexto separa la operación Colombia/Perú. Dentro de cada operación se
 * conserva el módulo País y la jerarquía País > Departamento > Ciudad.
 */

/**
 * @return array{paises:array<int,array<string,mixed>>,departamentos:array<int,array<string,mixed>>,ciudades:array<int,array<string,mixed>>}
 */
function ubicacionListarOpciones(mysqli $conn, int $idPaisOperacion): array
{
    $opciones = [
        'paises' => [],
        'departamentos' => [],
        'ciudades' => [],
    ];

    $stmt = $conn->prepare(
        "SELECT id_opcion, tipo, id_padre, nombre
         FROM configuraciones_servicio
         WHERE id_pais_operacion = ?
           AND tipo IN ('pais', 'departamento', 'ciudad')
           AND estado_registro = 'activo'
         ORDER BY FIELD(tipo, 'pais', 'departamento', 'ciudad'), orden, nombre"
    );
    $stmt->bind_param('i', $idPaisOperacion);
    $stmt->execute();
    $resultado = $stmt->get_result();

    while ($opcion = $resultado->fetch_assoc()) {
        $opcion['id_opcion'] = (int) $opcion['id_opcion'];
        $opcion['id_padre'] = (int) ($opcion['id_padre'] ?? 0);

        if ($opcion['tipo'] === 'pais') {
            $opciones['paises'][] = $opcion;
        } elseif ($opcion['tipo'] === 'departamento') {
            $opciones['departamentos'][] = $opcion;
        } elseif ($opcion['tipo'] === 'ciudad') {
            $opciones['ciudades'][] = $opcion;
        }
    }

    $stmt->close();

    return $opciones;
}

/**
 * @return array{id_pais:int,id_departamento:int,id_ciudad:int,pais:string,departamento:string,ciudad:string}|null
 */
function ubicacionValidarSeleccion(
    mysqli $conn,
    int $idPaisOperacion,
    int $idPais,
    int $idDepartamento,
    int $idCiudad
): ?array {
    if ($idPaisOperacion < 1 || $idPais < 1 || $idDepartamento < 1 || $idCiudad < 1) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT
            departamento.id_opcion AS id_departamento,
            ciudad.id_opcion AS id_ciudad,
            pais.id_opcion AS id_pais,
            pais.nombre AS pais,
            departamento.nombre AS departamento,
            ciudad.nombre AS ciudad
         FROM configuraciones_servicio AS ciudad
         INNER JOIN configuraciones_servicio AS departamento
            ON departamento.id_opcion = ciudad.id_padre
           AND departamento.tipo = 'departamento'
           AND departamento.id_pais_operacion = ciudad.id_pais_operacion
           AND departamento.estado_registro = 'activo'
         INNER JOIN configuraciones_servicio AS pais
            ON pais.id_opcion = departamento.id_padre
           AND pais.tipo = 'pais'
           AND pais.id_pais_operacion = departamento.id_pais_operacion
           AND pais.estado_registro = 'activo'
         WHERE ciudad.id_opcion = ?
           AND ciudad.tipo = 'ciudad'
           AND ciudad.id_padre = ?
           AND departamento.id_padre = ?
           AND ciudad.id_pais_operacion = ?
           AND ciudad.estado_registro = 'activo'
         LIMIT 1"
    );
    $stmt->bind_param('iiii', $idCiudad, $idDepartamento, $idPais, $idPaisOperacion);
    $stmt->execute();
    $ubicacion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ubicacion) {
        return null;
    }

    return [
        'id_pais' => (int) $ubicacion['id_pais'],
        'id_departamento' => (int) $ubicacion['id_departamento'],
        'id_ciudad' => (int) $ubicacion['id_ciudad'],
        'pais' => (string) $ubicacion['pais'],
        'departamento' => (string) $ubicacion['departamento'],
        'ciudad' => (string) $ubicacion['ciudad'],
    ];
}
