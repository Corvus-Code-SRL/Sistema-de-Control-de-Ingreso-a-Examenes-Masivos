<?php

return [
    /*
     * Sprint 1: la autenticación está simulada. Este es el UUID del usuario
     * de prueba que se usa como autor en la bitácora mientras no exista
     * login real. Se elimina cuando se implemente la autenticación.
     */
    'usuario_prueba' => env('SCIEM_USUARIO_PRUEBA'),

    /*
     * Gateway del SIS institucional. 'fake' usa datos locales de prueba.
     */
    'sis_driver' => env('SCIEM_SIS_DRIVER', 'fake'),
];