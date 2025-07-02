<?php
// edit_member.php

session_start();

// Ativar exibição de erros para depuração (REMOVA EM PRODUÇÃO)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once("conexao.php"); // Inclui seu arquivo de conexão

// Verifica se o usuário está logado e se tem permissão de 'admin'
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redireciona para o login se não for admin
    exit;
}

// --- BUSCAR PROFESSORES E LOCAIS DE TREINO PARA OS DROPDOWNS ---
$professors = [];
$stmt_professors = $conexao->query("SELECT id, name FROM professors ORDER BY name ASC");
if ($stmt_professors) {
    while ($row = $stmt_professors->fetch_assoc()) {
        $professors[] = $row;
    }
} else {
    // Apenas define uma mensagem de erro, não para o script aqui para permitir o restante do formulário
    $message = "Erro ao carregar professores: " . $conexao->error;
    $error_type = "error";
}

$training_locations = [];
$stmt_locations = $conexao->query("SELECT id, name FROM training_locations ORDER BY name ASC");
if ($stmt_locations) {
    while ($row = $stmt_locations->fetch_assoc()) {
        $training_locations[] = $row;
    }
} else {
    // Apenas define uma mensagem de erro, não para o script aqui
    $message = "Erro ao carregar locais de treino: " . $conexao->error;
    $error_type = "error";
}
// --- FIM DA BUSCA PARA DROPDOWNS ---


$member_id = $_GET['id'] ?? null; // Pega o ID do membro da URL
$member_data = null; // Dados do membro para preencher o formulário
$graduations_data = []; // Histórico de graduações
$message = ""; // Para mensagens de sucesso/erro (redefinido, se já houver msg da busca)
$error_type = ""; // 'success' ou 'error' (redefinido, se já houver msg da busca)

// Array de mapeamento de nomes de cordas para facilitar a exibição no formulário
// VERIFIQUE SE ESTES NOMES DE ARQUIVO E DESCRIÇÕES CORRESPONDEM EXATAMENTE AOS SEUS ARQUIVOS NA PASTA 'img/cordas/'
$corda_options = [
    // Adultos (Acima de 18 anos)
    'img/cordas/corda_adulto_crua.png'          => 'Corda Crua (Adulto)',
    'img/cordas/corda_adulto_amarelo_crua.png'  => 'Corda Amarelo/Crua (Adulto)',
    'img/cordas/corda_adulto_amarela.png'       => 'Corda Amarela (Adulto)',
    'img/cordas/corda_adulto_laranja_crua.png'  => 'Corda Laranja/Crua (Adulto)',
    'img/cordas/corda_adulto_laranja.png'       => 'Corda Laranja (Adulto)',
    'img/cordas/corda_adulto_azul_vermelho.png' => 'Corda Azul/Vermelho (Adulto)',
    'img/cordas/corda_adulto_azul.png'          => 'Corda Azul (Adulto)',
    'img/cordas/corda_adulto_verde.png'         => 'Corda Verde (Adulto)',
    'img/cordas/corda_adulto_roxo.png'          => 'Corda Roxo (Adulto)',
    'img/cordas/corda_adulto_marrom.png'        => 'Corda Marrom (Adulto)',
    'img/cordas/corda_adulto_preto.png'         => 'Corda Preto (Adulto)',

    // Adolescentes (10 a 16 anos)
    'img/cordas/corda_adolescente_crua.png'         => 'Corda Crua (Adolescente)',
    'img/cordas/corda_adolescente_amarelo_crua.png' => 'Corda Amarelo/Crua (Adolescente)',
    'img/cordas/corda_adolescente_laranja_crua.png' => 'Corda Laranja/Crua (Adolescente)',
    'img/cordas/corda_adolescente_azul_crua.png'    => 'Corda Azul/Crua (Adolescente)',
    'img/cordas/corda_adolescente_verde_crua.png'   => 'Corda Verde/Crua (Adolescente)',
    'img/cordas/corda_adolescente_roxo_crua.png'    => 'Corda Roxo/Crua (Adolescente)',
    'img/cordas/corda_adolescente_marrom_crua.png'  => 'Corda Marrom/Crua (Adolescente)',

    // Crianças (4 a 9 anos)
    'img/cordas/corda_crianca_crua.png'         => 'Corda Crua (Criança)',
    'img/cordas/corda_crianca_ponta_amarelo.png' => 'Corda Ponta Amarelo (Criança)',
    'img/cordas/corda_crianca_ponta_laranja.png' => 'Corda Ponta Laranja (Criança)',
    'img/cordas/corda_crianca_ponta_azul.png'    => 'Corda Ponta Azul (Criança)',
    'img/cordas/corda_crianca_ponta_verde.png'   => 'Corda Ponta Verde (Criança)',
    'img/cordas/corda_crianca_ponta_roxo.png'    => 'Corda Ponta Roxo (Criança)',
    'img/cordas/corda_crianca_ponta_marrom.png'  => 'Corda Ponta Marrom (Criança)',
];


// --- Carregar dados do membro (se ID for válido) ---
if ($member_id) {
    // SELECT atualizado para buscar professor_id e training_location_id
    $stmt = $conexao->prepare("SELECT id, full_name, nickname, birth_date, phone, instagram, email, professor_id, training_location_id, photo_path, current_cord_image, notes, status FROM capoeira_members WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $member_data = $result->fetch_assoc();
        } else {
            $message = "Membro não encontrado.";
            $error_type = "error";
            $member_id = null; // Invalida o ID para evitar processamento posterior
        }
        $stmt->close();
    } else {
        $message = "Erro ao preparar consulta de membro: " . $conexao->error;
        $error_type = "error";
    }

    // Carregar histórico de graduações
    if ($member_data) {
        $stmt_grad = $conexao->prepare("SELECT * FROM graduations_history WHERE member_id = ? ORDER BY graduation_year ASC, id ASC");
        if ($stmt_grad) {
            $stmt_grad->bind_param("i", $member_id);
            $stmt_grad->execute();
            $result_grad = $stmt_grad->get_result();
            while ($row_grad = $result_grad->fetch_assoc()) {
                $graduations_data[] = $row_grad;
            }
            $stmt_grad->close();
        } else {
            $message .= " Erro ao carregar histórico de graduações: " . $conexao->error;
            $error_type = "error";
        }
    }
}

// --- Processar Atualização do Formulário ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $member_id) {
    $full_name = trim($_POST['full_name'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? null);
    $phone = trim($_POST['phone'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $email = trim($_POST['email'] ?? '');
    // Pega os IDs dos dropdowns
    $professor_id = (int)($_POST['professor_id'] ?? 0);
    $training_location_id = (int)($_POST['training_location_id'] ?? 0);
    $current_cord_image = trim($_POST['current_cord_image'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    $old_photo_path = $member_data['photo_path']; // Salva o caminho da foto antiga
    $photo_path = $old_photo_path; // Assume que a foto não mudará, a menos que uma nova seja enviada

    // Validação básica
    if (empty($full_name) || $professor_id === 0 || $training_location_id === 0) { // IDs 0 indicam que nada foi selecionado
        $message = "Nome Completo, Professor e Local de Treino são campos obrigatórios.";
        $error_type = "error";
    } else {
        // --- Processar Upload da Nova Foto do Membro ---
        if (isset($_FILES['member_photo']) && $_FILES['member_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/member_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_tmp_name = $_FILES['member_photo']['tmp_name'];
            $file_name = uniqid() . '_' . basename($_FILES['member_photo']['name']);
            $destination = $upload_dir . $file_name;

            if (move_uploaded_file($file_tmp_name, $destination)) {
                $photo_path = $destination;
                // Apaga a foto antiga se uma nova foi carregada e a antiga existia
                if ($old_photo_path && file_exists($old_photo_path)) {
                    unlink($old_photo_path);
                }
            } else {
                $message .= " Erro ao mover o arquivo de foto.";
                $error_type = "error";
            }
        } elseif (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
             // Se a opção de remover foto foi marcada
             if ($old_photo_path && file_exists($old_photo_path)) {
                 unlink($old_photo_path);
             }
             $photo_path = null; // Define o caminho da foto como nulo no BD
         }


        // --- Atualizar Dados na Tabela `capoeira_members` ---
        // Query atualizada para usar professor_id e training_location_id
        $sql = "UPDATE capoeira_members SET full_name=?, nickname=?, birth_date=?, phone=?, instagram=?, email=?, professor_id=?, training_location_id=?, photo_path=?, current_cord_image=?, notes=?, status=? WHERE id=?";
        $stmt = $conexao->prepare($sql);

        if ($stmt) {
            // CORREÇÃO: String de tipos corrigida para ter 13 caracteres (6s, 2i, 3s, 2i)
            // Esta é a parte crucial que causou o ArgumentCountError
            // (linha 156 na versão anterior, pode ter mudado de número agora)
            
// ... seu código edit_member.php ...

                                if (!empty($grad_name) && $grad_year > 1900 && $grad_year <= date('Y')) {
                                    // *** INÍCIO DO BLOCO DE DEPURACAO - ADICIONE ISTO IMEDIATAMENTE ANTES DA LINHA 189 ***
                                    echo "<pre>DEBUG (L189 - Graduação):<br>";
                                    echo "  String de tipos: '" . "isi" . "' (Esperado 3 chars)<br>";
                                    echo "  Comprimento da string de tipos: " . strlen("isi") . "<br>";
                                    echo "  Contagem de variáveis: " . count([$member_id, $grad_name, $grad_year]) . "<br>";
                                    echo "  Valores das variáveis:<br>";
                                    var_dump($member_id, $grad_name, $grad_year);
                                    echo "</pre>";
                                    die("Parando para depurar o bind_param da graduação."); // ESTA LINHA VAI PARAR O SCRIPT AQUI
                                    // *** FIM DO BLOCO DE DEPURACAO ***

                                    // Linha 189: O bind_param problemático
                                    $insert_graduations_stmt->bind_param("isi", $member_id, $grad_name, $grad_year);
                                    $insert_graduations_stmt->execute();
                                }

// ... restante do seu edit_member.php ...            
            
            
            
            
            
            if (!$stmt->bind_param(
                "ssssssiissii", // A string de tipos correta (13 caracteres)
                $full_name,
                $nickname,
                $birth_date,
                $phone,
                $instagram,
                $email,
                $professor_id, // Tipo 'i' (integer)
                $training_location_id, // Tipo 'i' (integer)
                $photo_path,
                $current_cord_image,
                $notes,
                $status,
                $member_id // Tipo 'i' (integer) no WHERE
            )) {
                die("Erro ao vincular parâmetros: " . $stmt->error);
            }

            if ($stmt->execute()) {
                $message = "Membro '{$full_name}' atualizado com sucesso!";
                $error_type = "success";

                // Re-carrega os dados do membro após a atualização para exibir o estado mais recente
                // SELECT atualizado para buscar professor_id e training_location_id
                $stmt_reload = $conexao->prepare("SELECT id, full_name, nickname, birth_date, phone, instagram, email, professor_id, training_location_id, photo_path, current_cord_image, notes, status FROM capoeira_members WHERE id = ?");
                $stmt_reload->bind_param("i", $member_id);
                $stmt_reload->execute();
                $member_data = $stmt_reload->get_result()->fetch_assoc();
                $stmt_reload->close();

                // --- Atualizar Histórico de Graduações ---
                $conexao->begin_transaction();
                try {
                    // 1. Exclui todas as graduações existentes para este membro
                    $delete_graduations_sql = "DELETE FROM graduations_history WHERE member_id = ?";
                    $delete_graduations_stmt = $conexao->prepare($delete_graduations_sql);
                    if ($delete_graduations_stmt) {
                        $delete_graduations_stmt->bind_param("i", $member_id);
                        $delete_graduations_stmt->execute();
                        $delete_graduations_stmt->close();
                    } else {
                        throw new Exception("Erro ao preparar exclusão de histórico de graduações: " . $conexao->error);
                    }

                    // 2. Insere as novas/atualizadas graduações
                    if (isset($_POST['graduation_name']) && is_array($_POST['graduation_name'])) {
                        $insert_graduations_sql = "INSERT INTO graduations_history (member_id, graduation_name, graduation_year) VALUES (?, ?, ?)";
                        $insert_graduations_stmt = $conexao->prepare($insert_graduations_sql);
                        if ($insert_graduations_stmt) {
                            foreach ($_POST['graduation_name'] as $key => $grad_name) {
                                $grad_name = trim($grad_name);
                                $grad_year = (int)($_POST['graduation_year'][$key] ?? 0);

                                if (!empty($grad_name) && $grad_year > 1900 && $grad_year <= date('Y')) {
                                    // CORREÇÃO AQUI: String de tipos para graduações (3 caracteres)
                                    // Esta é a outra chamada bind_param que pode causar erro
                                    // (provavelmente linha 188/201 dependendo das versões)
                                    $insert_graduations_stmt->bind_param("isi", $member_id, $grad_name, $grad_year);
                                    $insert_graduations_stmt->execute();
                                }
                            }
                            $insert_graduations_stmt->close();
                        } else {
                            throw new Exception("Erro ao preparar inserção de graduações: " . $conexao->error);
                        }
                    }
                    $conexao->commit(); // Confirma a transação
                    // Re-carrega as graduações para exibir no formulário após a atualização
                    $graduations_data = [];
                    $stmt_grad_reload = $conexao->prepare("SELECT * FROM graduations_history WHERE member_id = ? ORDER BY graduation_year ASC, id ASC");
                    $stmt_grad_reload->bind_param("i", $member_id);
                    $stmt_grad_reload->execute();
                    $result_grad_reload = $stmt_grad_reload->get_result();
                    while ($row_grad = $result_grad_reload->fetch_assoc()) {
                        $graduations_data[] = $row_grad;
                    }
                    $stmt_grad_reload->close();

                } catch (Exception $e) {
                    $conexao->rollback(); // Desfaz a transação em caso de erro
                    $message .= " Erro ao atualizar histórico de graduações: " . $e->getMessage();
                    $error_type = "error";
                }

            } else {
                $message = "Erro ao atualizar membro: " . $stmt->error;
                $error_type = "error";
            }
            $stmt->close();
        } else {
            $message = "Erro ao preparar a consulta para membro: " . $conexao->error;
            $error_type = "error";
        }
    }
}

$conexao->close();

// Preencher variáveis para o formulário se os dados do membro foram carregados
$full_name = $member_data['full_name'] ?? '';
$nickname = $member_data['nickname'] ?? '';
$birth_date = $member_data['birth_date'] ?? '';
$phone = $member_data['phone'] ?? '';
$instagram = $member_data['instagram'] ?? '';
$email = $member_data['email'] ?? '';
// Agora pegamos os IDs do professor e local para pré-selecionar os dropdowns
$professor_id_selected = $member_data['professor_id'] ?? 0;
$training_location_id_selected = $member_data['training_location_id'] ?? 0;
$current_cord_image_selected = $member_data['current_cord_image'] ?? ''; // O valor selecionado no dropdown
$notes = $member_data['notes'] ?? '';
$status = $member_data['status'] ?? 'active';
$current_member_photo = $member_data['photo_path'] ?? null;

?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <title>Editar Membro</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .container { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 800px; margin: 20px auto; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .message { text-align: center; padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        form label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        form input[type="text"],
        form input[type="email"],
        form input[type="tel"],
        form input[type="date"],
        form select,
        form textarea {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        form input[type="file"] { margin-bottom: 15px; }
        form textarea { resize: vertical; min-height: 80px; }
        form button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-right: 10px; }
        form button:hover { background-color: #0056b3; }
        .form-group { margin-bottom: 15px; }
        .flex-group { display: flex; gap: 20px; margin-bottom: 15px; }
        .flex-group > div { flex: 1; }

        /* Estilos para o bloco de graduações */
        .graduations-section { border: 1px dashed #ccc; padding: 15px; margin-top: 20px; border-radius: 5px; }
        .graduations-section h3 { margin-top: 0; color: #666; }
        .graduation-item { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        .graduation-item input[type="text"] { flex: 2; }
        .graduation-item input[type="number"] { flex: 1; max-width: 100px; }
        .remove-grad-btn { background-color: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
        .add-grad-btn { background-color: #28a745; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; margin-top: 10px; }

        .current-photo-section { text-align: center; margin-bottom: 20px; }
        .current-photo-section img { max-width: 150px; height: auto; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 10px; }
        .current-photo-section p { margin: 0; color: #666; }
        .remove-photo-checkbox { margin-left: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Editar Membro do Grupo de Capoeira</h2>

        <?php if (!empty($message)): ?>
            <p class="message <?php echo $error_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </p>
        <?php endif; ?>

        <?php if (!$member_id): ?>
            <p class="error">ID do membro não especificado ou inválido.</p>
            <div style="text-align: center; margin-top: 30px;"><a href="manage_members.php">Voltar para Gerenciar Membros</a></div>
        <?php else: ?>
            <form action="edit_member.php?id=<?php echo $member_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="full_name">Nome Completo:</label>
                    <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($full_name); ?>">
                </div>

                <div class="flex-group">
                    <div>
                        <label for="nickname">Apelido (Nome de Batismo):</label>
                        <input type="text" id="nickname" name="nickname" value="<?php echo htmlspecialchars($nickname); ?>">
                    </div>
                    <div>
                        <label for="birth_date">Data de Nascimento:</label>
                        <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($birth_date); ?>">
                    </div>
                </div>

                <div class="flex-group">
                    <div>
                        <label for="phone">Telefone:</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                    </div>
                    <div>
                        <label for="instagram">Instagram (@):</label>
                        <input type="text" id="instagram" name="instagram" value="<?php echo htmlspecialchars($instagram); ?>">
                    </div>
                    <div>
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="professor_id">Professor (dele):</label>
                    <select id="professor_id" name="professor_id" required>
                        <option value="">Selecione um Professor</option>
                        <?php foreach ($professors as $professor): ?>
                            <option value="<?php echo htmlspecialchars($professor['id']); ?>"
                                <?php echo ($professor_id_selected == $professor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($professor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="training_location_id">Local de Treino:</label>
                    <select id="training_location_id" name="training_location_id" required>
                        <option value="">Selecione um Local de Treino</option>
                        <?php foreach ($training_locations as $location): ?>
                            <option value="<?php echo htmlspecialchars($location['id']); ?>"
                                <?php echo ($training_location_id_selected == $location['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>


                <div class="form-group">
                    <label for="current_cord_image">Graduação Atual (Imagem da Corda):</label>
                    <select id="current_cord_image" name="current_cord_image">
                        <option value="">Selecione a Corda Atual</option>
                        <?php foreach ($corda_options as $path => $name): ?>
                            <option value="<?php echo htmlspecialchars($path); ?>" <?php echo ($current_cord_image_selected === $path) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Foto do Membro:</label> <?php if ($current_member_photo && file_exists($current_member_photo)): ?>
                        <div class="current-photo-section">
                            <img src="<?php echo htmlspecialchars($current_member_photo); ?>" alt="Foto atual do membro">
                            <p>Foto atual</p>
                            <label><input type="checkbox" name="remove_photo" value="1" class="remove-photo-checkbox"> Remover foto atual</label>
                        </div>
                    <?php else: ?>
                        <p>Nenhuma foto cadastrada.</p>
                    <?php endif; ?>
                    <label for="member_photo">Escolher nova foto:</label> <input type="file" id="member_photo" name="member_photo" accept="image/*">
                </div>

                <div class="form-group">
                    <label for="notes">Observações / Histórico Adicional:</label>
                    <textarea id="notes" name="notes"><?php echo htmlspecialchars($notes); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>

                <div class="graduations-section">
                    <h3>Histórico de Graduações</h3>
                    <div id="graduations-container">
                        <?php if (empty($graduations_data)): ?>
                            <div class="graduation-item">
                                <input type="text" name="graduation_name[]" placeholder="Nome da Graduação (ex: Corda Amarela)">
                                <input type="number" name="graduation_year[]" placeholder="Ano (AAAA)" min="1900" max="<?php echo date('Y'); ?>">
                                <button type="button" class="remove-grad-btn" style="display:none;">Remover</button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($graduations_data as $grad): ?>
                                <div class="graduation-item">
                                    <input type="text" name="graduation_name[]" value="<?php echo htmlspecialchars($grad['graduation_name']); ?>" placeholder="Nome da Graduação">
                                    <input type="number" name="graduation_year[]" value="<?php echo htmlspecialchars($grad['graduation_year']); ?>" placeholder="Ano (AAAA)" min="1900" max="<?php echo date('Y'); ?>">
                                <button type="button" class="remove-grad-btn">Remover</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-graduation-btn" class="add-grad-btn">Adicionar Outra Graduação</button>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit">Atualizar Membro</button>
                    <button type="button" onclick="window.location.href='manage_members.php'">Cancelar e Voltar</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addGradBtn = document.getElementById('add-graduation-btn');
            const graduationsContainer = document.getElementById('graduations-container');

            addGradBtn.addEventListener('click', function() {
                addGraduationRow();
            });

            graduationsContainer.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-grad-btn')) {
                    e.target.closest('.graduation-item').remove();
                    updateRemoveButtons();
                }
            });

            function addGraduationRow() {
                const newGradRow = document.createElement('div');
                newGradRow.classList.add('graduation-item');
                newGradRow.innerHTML = `
                    <input type="text" name="graduation_name[]" placeholder="Nome da Graduação (ex: Corda Amarela)">
                    <input type="number" name="graduation_year[]" placeholder="Ano (AAAA)" min="1900" max="<?php echo date('Y'); ?>">
                    <button type="button" class="remove-grad-btn">Remover</button>
                `;
                graduationsContainer.appendChild(newGradRow);
                updateRemoveButtons();
            }

            function updateRemoveButtons() {
                const removeButtons = document.querySelectorAll('.remove-grad-btn');
                // Mostra o botão remover para todos, exceto o primeiro se for o único
                // ou se houver apenas 1 item mas ele já veio do banco (ou seja, graduations_data não estava vazio)
                if (removeButtons.length > 1 || (removeButtons.length === 1 && <?php echo json_encode(!empty($graduations_data)); ?>)) {
                    removeButtons.forEach(btn => btn.style.display = 'inline-block');
                } else if (removeButtons.length === 1) {
                    removeButtons[0].style.display = 'none'; // Esconde se for o único item e não veio do banco
                }
            }

            // Inicializa a exibição dos botões remover ao carregar a página
            updateRemoveButtons();

            // Adiciona listener para o checkbox de remover foto
            const removePhotoCheckbox = document.querySelector('input[name="remove_photo"]');
            const memberPhotoInput = document.getElementById('member_photo');
            if (removePhotoCheckbox && memberPhotoInput) {
                removePhotoCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        memberPhotoInput.disabled = true; // Desabilita o input de upload se remover for marcado
                    } else {
                        memberPhotoInput.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>
