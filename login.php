<?php
// login.php

session_start();

// Ativar exibição de erros para depuração (REMOVA EM PRODUÇÃO)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once("conexao.php"); // Inclui seu arquivo de conexão

$error_message = ""; // Variável para armazenar mensagens de erro

// Verifica se o formulário foi submetido via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? ''; // Usa operador null coalescing para evitar "Undefined index"
    $password = $_POST['password'] ?? '';

    // Validação básica
    if (empty($username) || empty($password)) {
        $error_message = "Por favor, preencha todos os campos.";
    } else {
        // Usa Prepared Statements para segurança contra SQL Injection
        $stmt = $conexao->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        try {
            $stmt->bind_param("s", $username); // "s" indica que é uma string
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verifica a senha usando password_verify (função segura para hashes)
                if (password_verify($password, $user['password'])) {
                    // Login bem-sucedido: Armazena dados na sessão
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role']; // Armazena o papel do usuário

                    // Redireciona para a página do painel de controle
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error_message = "Usuário ou senha incorretos.";
                }
            } else {
                $error_message = "Usuário ou senha incorretos.";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $error_message = "Erro de banco de dados: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css" href="style.css">
    <title>Login</title>
    <style>
        /* Estilos básicos para o formulário de login */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); width: 300px; text-align: center; }
        .login-container h2 { margin-bottom: 20px; color: #333; }
        .login-container label { display: block; text-align: left; margin-bottom: 5px; color: #555; }
        .login-container input[type="text"], .login-container input[type="password"] { width: calc(100% - 20px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
        .login-container button { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .login-container button:hover { background-color: #0056b3; }
        .error-message { color: red; margin-top: 10px; }
			 /* ... seus estilos existentes ... */

        .login-logo {
            max-width: 150px; /* Ajuste a largura máxima conforme necessário */
            height: auto;     /* Mantém a proporção da imagem */
            margin-bottom: 20px; /* Espaço abaixo do logo e acima do título */
            display: block;   /* Para centralizar */
            margin-left: auto; /* Para centralizar */
            margin-right: auto; /* Para centralizar */
        }        
        
        
    </style>
</head>
<body>
    <div class="login-container">
        <img src="img/logo-M.png" alt="Logo do Grupo de Capoeira" class="login-logo">
        <h2>Login</h2>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="" method="POST">
            <label for="username">Usuário:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Senha:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
