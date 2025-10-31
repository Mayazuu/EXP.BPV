<?php
session_start();

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$mensaje = $_GET['mensaje'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar Contraseña - Bufete Popular</title>
    <link href="../css/style.css" rel="stylesheet">
    <link href="styleInicio.css" rel="stylesheet">
</head>
<body>

<div class="container-cambio">
<?php include('../navbar.php'); ?>
<?php include('../boton_ayuda.php'); ?>

    <div class="header-cambio">
        <img src="../img/logo.png" alt="Logo Bufete Popular">
        <h2>Cambiar Contraseña</h2>
        <p>Bienvenido/a, <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong></p>
    </div>

    <?php if ($mensaje): ?>
        <div class="mensaje-exito">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mensaje-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="procesar_cambio_contrasena.php" method="POST" id="formCambioPass">

        <div class="form-group">
            <label for="contrasena_actual">Contraseña Actual</label>
            <input type="password" name="contrasena_actual" id="contrasena_actual" required>
        </div>

        <div class="form-group">
            <label for="contrasena_nueva">Nueva Contraseña</label>
            <input type="password" name="contrasena_nueva" id="contrasena_nueva" required>
            <div class="password-strength" id="strengthBar"></div>
        </div>

        <div class="password-requirements">
            <h4>Requisitos de Contraseña Segura:</h4>
            <div class="requirement" id="req-length">Mínimo 8 caracteres</div>
            <div class="requirement" id="req-uppercase">Al menos una letra mayúscula (A-Z)</div>
            <div class="requirement" id="req-lowercase">Al menos una letra minúscula (a-z)</div>
            <div class="requirement" id="req-number">Al menos un número (0-9)</div>
            <div class="requirement" id="req-special">Al menos un símbolo especial (!@#$%^&*)</div>
        </div>

        <div class="form-group">
            <label for="contrasena_confirmar">Confirmar Nueva Contraseña</label>
            <input type="password" name="contrasena_confirmar" id="contrasena_confirmar" required>
        </div>

        <button type="submit" id="btnSubmit">Cambiar Contraseña</button>
        <a href="dashboard.php" class="btn-secondary">Volver al Dashboard</a>
    </form>
</div>

<?php include('../footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passInput = document.getElementById('contrasena_nueva');
    const confirmInput = document.getElementById('contrasena_confirmar');
    const form = document.getElementById('formCambioPass');
    const btnSubmit = document.getElementById('btnSubmit');

    passInput.addEventListener('input', function() {
        validatePassword(this.value);
    });

    form.addEventListener('submit', function(e) {
        const password = passInput.value;
        const confirm = confirmInput.value;

        if (password !== confirm) {
            e.preventDefault();
            alert('Las contraseñas no coinciden');
            return false;
        }

        if (!isPasswordValid(password)) {
            e.preventDefault();
            alert('La contraseña no cumple con todos los requisitos de seguridad');
            return false;
        }
    });

    function validatePassword(password) {
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };

        updateRequirement('req-length', requirements.length);
        updateRequirement('req-uppercase', requirements.uppercase);
        updateRequirement('req-lowercase', requirements.lowercase);
        updateRequirement('req-number', requirements.number);
        updateRequirement('req-special', requirements.special);

        const strength = Object.values(requirements).filter(Boolean).length;
        updateStrengthBar(strength);

        btnSubmit.disabled = !isPasswordValid(password);
    }

    function updateRequirement(id, isValid) {
        const element = document.getElementById(id);
        if (isValid) {
            element.classList.add('valid');
            element.classList.remove('invalid');
        } else {
            element.classList.add('invalid');
            element.classList.remove('valid');
        }
    }

    function updateStrengthBar(strength) {
        const bar = document.getElementById('strengthBar');
        bar.className = 'password-strength';

        if (strength <= 2) {
            bar.classList.add('strength-weak');
        } else if (strength <= 4) {
            bar.classList.add('strength-medium');
        } else {
            bar.classList.add('strength-strong');
        }
    }

    function isPasswordValid(password) {
        return password.length >= 8 &&
            /[A-Z]/.test(password) &&
            /[a-z]/.test(password) &&
            /[0-9]/.test(password) &&
            /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
    }
});
</script>
</body>
</html>