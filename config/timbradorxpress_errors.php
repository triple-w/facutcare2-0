<?php
// config/timbradorxpress_errors.php

return [

    // En cualquier operación
    'general' => [
        '300' => 'API KEY inválido o inexistente.',
    ],

    // 6.1 Timbrado
    'timbrado' => [
        '200' => 'Solicitud procesada con éxito.',
        '301' => 'XML mal formado.',
        '302' => 'El sello del emisor no es válido.',
        '303' => 'El RFC del CSD del Emisor no corresponde al RFC del Emisor.',
        '304' => 'CSD del Emisor ha sido revocado.',
        '305' => 'La fecha de emisión no está dentro de la vigencia del CSD del Emisor.',
        '306' => 'La llave utilizada para sellar debe ser un CSD.',
        '307' => 'El CFDI contiene un timbre previo.',
        '308' => 'El CSD del Emisor no ha sido firmado por uno de los Certificados de Autoridad del SAT.',
        '401' => 'El rango de la fecha de generación no debe ser mayor a 72 horas para la emisión del timbre.',
        '402' => 'RFC del emisor no se encuentra en el régimen de contribuyentes (LCO).',
    ],

    // Timbrado - CFDI de Retenciones (mismos mensajes con prefijo T)
    'timbrado_retenciones' => [
        '200'  => 'Solicitud procesada con éxito.',
        'T301' => 'XML mal formado.',
        'T302' => 'El sello del emisor no es válido.',
        'T303' => 'El RFC del CSD del Emisor no corresponde al RFC del Emisor.',
        'T304' => 'CSD del Emisor ha sido revocado.',
        'T305' => 'La fecha de emisión no está dentro de la vigencia del CSD del Emisor.',
        'T306' => 'La llave utilizada para sellar debe ser un CSD.',
        'T307' => 'El CFDI contiene un timbre previo.',
        'T308' => 'El CSD del Emisor no ha sido firmado por uno de los Certificados de Autoridad del SAT.',
        'T401' => 'El rango de la fecha de generación no debe ser mayor a 72 horas para la emisión del timbre.',
        'T402' => 'RFC del emisor no se encuentra en el régimen de contribuyentes (LCO).',
    ],

    // 6.2 Cancelación (CFDI)
    'cancelacion' => [
        '201'   => 'Solicitud de cancelación exitosa.',
        '202'   => 'Se considera previamente cancelado (estatus Cancelado ante el SAT).',
        '203'   => 'UUID: no corresponde el RFC del emisor y de quien solicita la cancelación.',
        '205'   => 'No existe. (Nota del doc: puede requerir esperar hasta 72 hrs para verse “Vigente” en SAT).',

        // Códigos “CA…”
        'CA1000' => 'El XML proporcionado está mal formado o es inválido.',
        'CA2000' => 'No fue posible cancelar: intermitencia del SAT, intente más tarde (el SAT devuelve detalle).',
        'CA2100' => 'No fue posible cancelar: intente más tarde; si persiste contacte a soporte técnico.',
        'CA2300' => 'No fue posible cancelar: intente más tarde; si persiste contacte a soporte técnico.',

        'CA203'  => 'El UUID tiene un fallo correspondiente al emisor (acuse con error en folio 203).',
        'CA204'  => 'El SAT no ve que el UUID sea aplicable para la cancelación.',
        'CA205'  => 'El UUID no existe (acuse con error en folio 205).',

        'CA300'  => 'La autenticación es incorrecta.',
        'CA301'  => 'El XML está mal formado o es incorrecto.',
        'CA302'  => 'Sello mal formado o inválido.',
        'CA303'  => 'Sello no corresponde a emisor o caduco.',
        'CA304'  => 'Certificado revocado o caduco.',
        'CA305'  => 'La fecha de emisión no está dentro de la vigencia del CSD del Emisor.',
        'CA306'  => 'El certificado no es de tipo CSD.',
        'CA307'  => 'El CFDI contiene un timbre previo.',
        'CA308'  => 'Certificado no expedido por el SAT.',
        'CA309'  => 'No existe cancelación que corresponda con el ID proporcionado.',
        'CASD'   => 'Acuse sin descripción específica.',
        'CACFDI33' => 'Problemas con los campos (posibles causas: usando FIEL en lugar de CSD / faltan campos requeridos).',
    ],

    // Cancelación - CFDI de Retenciones
    'cancelacion_retenciones' => [
        'CR1000' => 'Error al autenticar el servicio de cancelación.',
        'CR1001' => 'Error durante la cancelación servicio SAT.',
        'CR1002' => 'Error: el objeto de cancelación viene vacío.',
        'CR1003' => 'Error: dato folios a cancelar es inválido.',
        'CR1004' => 'Error: el resultado del servicio del SAT es vacío o inválido.',
        'CR1005' => 'Error: el folio de seguimiento es inválido.',
        'CR1006' => 'Mensaje SAT.',
        'CR1201' => 'UUID: Solicitud de cancelación correcta.',
        'CR1202' => 'UUID: Previamente cancelado.',
        'CR1203' => 'UUID no corresponde con el emisor.',
        'CR1205' => 'UUID no existe.',
        'CR1300' => 'Autenticación no válida.',
        'CR1301' => 'XML mal formado.',
        'CR1302' => 'Estructura de folios no válida.',
        'CR1303' => 'Estructura de RFC no válida.',
        'CR1304' => 'Estructura de fecha no válida.',
        'CR1305' => 'Certificado no corresponde al emisor.',
        'CR1306' => 'Certificado no vigente.',
        'CR1307' => 'Uso de FIEL no permitido.',
        'CR1308' => 'Certificado revocado o caduco.',
        'CR1309' => 'Firma mal formada o inválida.',
    ],

    // 6.3 Consulta
    'consulta' => [
        // Consultar Autorizaciones Pendientes
        '100' => 'Autorizaciones pendientes entregadas.',
        '101' => 'No hay autorizaciones pendientes.',
        '997' => 'Parámetros inválidos.',
        '999' => 'Error interno.',

        // Consultar CFDIs Relacionados (nota: mismos códigos pero 102 aplica aquí)
        '102' => 'El folio fiscal no pertenece al Emisor.',
    ],
];
