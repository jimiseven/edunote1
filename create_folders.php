<?php
// Define la estructura de carpetas y subcarpetas
$folders = [
    'assets',
    'assets/css',
    'assets/js',
    'assets/img',
    'config',
    'includes',
    'admin',
    'profesor'
];

// Creación de carpetas
foreach ($folders as $folder) {
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
        echo "Carpeta creada: $folder\n";
    } else {
        echo "La carpeta ya existe: $folder\n";
    }
}

echo "Estructura de carpetas creada exitosamente.";
?>
