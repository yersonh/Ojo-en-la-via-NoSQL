#!/bin/bash

# Crear directorios necesarios
mkdir -p /tmp/sessions
mkdir -p storage/logs
mkdir -p storage/framework/{cache,sessions,views}

# Establecer permisos
chmod -R 777 /tmp/sessions
chmod -R 777 storage

# Verificar configuración
echo "🔍 Verificando configuración..."
echo "PHP Version: $(php -v | head -n 1)"
echo "Extensiones cargadas:"
php -m | grep -E "mongodb|redis|curl"

# Iniciar servidor PHP
echo "🚀 Iniciando servidor PHP en puerto $PORT"
exec php -S 0.0.0.0:$PORT -t .