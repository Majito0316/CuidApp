<?php
require_once __DIR__ . '/includes/session.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CuidApp</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1 class="logo">CuidApp</h1>

        <div class="heart">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 180">
                <path d="M100 170 L30 90 A40 40 0 1 1 100 40 A40 40 0 1 1 170 90 Z" fill="#2ca6a4"/>
                <polyline points="40,90 70,90 80,70 100,120 120,60 140,90 160,90" 
                          fill="none" stroke="white" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <p class="description">
            Solicita medicamentos en línea, recibe recordatorios y accede a apoyo práctico para pacientes y cuidadores.
        </p>

        <a href="login.php" class="btn">COMENZAR</a>
    </div>
</body>
</html>

