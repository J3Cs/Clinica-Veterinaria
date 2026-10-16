<?php
namespace App\Controllers;

class HomeController {
    public function index() {
        $mensaje = "¡Aplicación corriendo de forma exitosa sin importar si estás en Docker o XAMPP!";
        require_once __DIR__ . '/../Views/home.php';
    }
}