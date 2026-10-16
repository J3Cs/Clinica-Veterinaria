<?php
// Helper rápido para URLs relativas seguras en vistas
$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP MVC Dual Scaffold</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/css/style.css">
</head>
<body>
    <div class="card">
        <h1>🚀 PHP Puro MVC (Docker & XAMPP)</h1>
        <p><?php echo htmlspecialchars($mensaje); ?></p>
        <button id="btnTest">Probar JavaScript</button>
    </div>
    <script src="<?php echo $base_url; ?>/js/main.js"></script>
</body>
</html>