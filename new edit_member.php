<?php
// edit_member.php (Final - Lógica de Professores)

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once("conexao.php");

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
$member_id = (int)($_GET['id'] ?? 0);
if ($member_id === 0) {
    header("Location: manage_members.php");
    exit;
}

$message = '';
$error_type = '';

$professors = [];
$training_locations = [];
$member_data = null;
$graduations_data = [];
try {
    $result_professors = $conexao->query("SELECT id, name FROM professors ORDER BY name ASC");
    while ($row = $result_professors->fetch_assoc()) { $professors[] = $row; }
    $result_locations = $conexao->query("SELECT id, name FROM training_locations ORDER BY name ASC");
    while ($row = $result_locations->fetch_assoc()) { $training_locations[] = $row; }

    $stmt_member = $conexao->prepare("SELECT * FROM capoeira_members WHERE id = ?");
    $stmt_member->bind_param("i", $member_id);
    $stmt_member->execute();
    $result_member = $stmt_member->get_result();
    if ($result_member->num_rows === 0) { throw new Exception("Membro não encontrado."); }
    $member_data = $result_member->fetch_assoc();
    $stmt_member->close();

    $stmt_grad = $conexao->prepare("SELECT * FROM graduations_history WHERE member_id = ? ORDER BY graduation_year ASC, id ASC");
    $stmt_grad->bind_param("i", $member_id);
    $stmt_grad->execute();
    $result_grad = $stmt_grad->get_result();
    while($row = $result_grad->fetch_assoc()){ $graduations_data[] = $row; }
    $stmt_grad->close();
} catch (Exception $e) {
    die("Erro fatal ao carregar dados: " . $e->getMessage());
}

$corda_options = [ /* Cole seu array de cordas aqui */ 
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conexao->begin_transaction();
    try {
        $full_name = trim($_POST['full_name'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $phone = trim($_POST['phone'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $email = trim($_POST['email'] ?? '');
        //$professor_id = !empty($_POST['professor_id']) ? (int)$_POST['professor_id'] : null; // MUDANÇA
        if (isset($_POST['professor_id']) && $_POST['professor_id'] !== '') {
    		$professor_id = (int)$_POST['professor_id'];
			} else {
   		 $professor_id = null; // Garante que será NULL se "-- Nenhum --" for selecionado
			}
        $training_location_id = (int)($_POST['training_location_id'] ?? 0);
        $current_cord_image = trim($_POST['current_cord_image'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if (empty($full_name)) { throw new Exception("Nome Completo é obrigatório."); }

        $photo_path = $member_data['photo_path'];
        if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
            if ($photo_path && file_exists($photo_path)) { unlink($photo_path); }
            $photo_path = null;
        } elseif (isset($_FILES['member_photo']) && $_FILES['member_photo']['error'] === UPLOAD_ERR_OK) {
            if ($photo_path && file_exists($photo_path)) { unlink($photo_path); }
            $upload_dir = 'uploads/member_photos/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            $file_name = $member_id . '_' . uniqid() . '_' . basename($_FILES['member_photo']['name']);
            $destination = $upload_dir . $file_name;
            if (!move_uploaded_file($_FILES['member_photo']['tmp_name'], $destination)) { throw new Exception("Erro ao mover arquivo de foto."); }
            $photo_path = $destination;
        }

        // MUDANÇA: Query de UPDATE com professor_id
        $sql_update = "UPDATE capoeira_members SET full_name=?, nickname=?, birth_date=?, phone=?, instagram=?, email=?, professor_id=?, training_location_id=?, current_cord_image=?, notes=?, status=?, photo_path=? WHERE id=?";
        $stmt_update = $conexao->prepare($sql_update);
        // MUDANÇA: bind_param ajustado
        $stmt_update->bind_param("ssssssiissssi", $full_name, $nickname, $birth_date, $phone, $instagram, $email, $professor_id, $training_location_id, $current_cord_image, $notes, $status, $photo_path, $member_id);
        $stmt_update->execute();
        $stmt_update->close();

        $stmt_delete_grad = $conexao->prepare("DELETE FROM graduations_history WHERE member_id = ?");
        $stmt_delete_grad->bind_param("i", $member_id);
        $stmt_delete_grad->execute();
        $stmt_delete_grad->close();
        if (isset($_POST['graduation_name']) && is_array($_POST['graduation_name'])) {
            $sql_insert_grad = "INSERT INTO graduations_history (member_id, graduation_name, graduation_year) VALUES (?, ?, ?)";
            $stmt_insert_grad = $conexao->prepare($sql_insert_grad);
            foreach ($_POST['graduation_name'] as $key => $grad_name) {
                $grad_name = trim($grad_name);
                $grad_year = (int)($_POST['graduation_year'][$key] ?? 0);
                if (!empty($grad_name) && $grad_year > 1900) {
                    $stmt_insert_grad->bind_param("isi", $member_id, $grad_name, $grad_year);
                    $stmt_insert_grad->execute();
                }
            }
            $stmt_insert_grad->close();
        }

        $conexao->commit();
        $_SESSION['message'] = "Membro '" . htmlspecialchars($full_name) . "' atualizado com sucesso!";
        $_SESSION['error_type'] = "success";
        header("Location: manage_members.php");
        exit;
    } catch (Exception $e) {
        $conexao->rollback();
        $message = $e->getMessage();
        $error_type = "error";
    }
}
$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    
    <link rel="stylesheet" href="style.css">
<title>Editar Membro</title></head>
<body>
    <div class="container">
        <h2>Editar Membro: <?php echo htmlspecialchars($member_data['full_name']); ?></h2>
        <?php if(!empty($message)): ?><p class="message <?php echo $error_type; ?>"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
        <form action="edit_member.php?id=<?php echo $member_id; ?>" method="POST" enctype="multipart/form-data">
            <div class="flex-group">
                <div class="form-group"><label for="full_name">Nome Completo:</label><input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($member_data['full_name']); ?>"></div>
                <div class="form-group"><label for="nickname">Apelido:</label><input type="text" id="nickname" name="nickname" value="<?php echo htmlspecialchars($member_data['nickname']); ?>"></div>
            </div>
            <div class="form-group"><label for="birth_date">Data de Nascimento:</label><input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($member_data['birth_date']); ?>"></div>
            <div class="flex-group">
                <div class="form-group"><label for="phone">Telefone:</label><input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($member_data['phone']); ?>"></div>
                <div class="form-group"><label for="email">Email:</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($member_data['email']); ?>"></div>
                <div class="form-group"><label for="instagram">Instagram:</label><input type="text" id="instagram" name="instagram" value="<?php echo htmlspecialchars($member_data['instagram']); ?>"></div>
            </div>
            <div class="form-group">
                <label for="professor_id">Professor:</label>
                <select id="professor_id" name="professor_id">
                    <option value="">-- Nenhum --</option>
                    <?php foreach ($professors as $professor): ?>
                        <option value="<?php echo $professor['id']; ?>" <?php echo ($member_data['professor_id'] == $professor['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($professor['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-group">
                <div class="form-group"><label for="training_location_id">Local de Treino:</label><select id="training_location_id" name="training_location_id" required><option value="">Selecione um local</option><?php foreach ($training_locations as $location): ?><option value="<?php echo $location['id']; ?>" <?php echo ($member_data['training_location_id'] == $location['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($location['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="current_cord_image">Graduação Atual:</label><select id="current_cord_image" name="current_cord_image"><option value="">Selecione</option><?php foreach ($corda_options as $path => $name): ?><option value="<?php echo htmlspecialchars($path); ?>" <?php echo ($member_data['current_cord_image'] == $path) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-group"><label for="status">Status:</label><select id="status" name="status"><option value="active" <?php echo ($member_data['status'] == 'active') ? 'selected' : ''; ?>>Ativo</option><option value="inactive" <?php echo ($member_data['status'] == 'inactive') ? 'selected' : ''; ?>>Inativo</option></select></div>
            <div class="form-group"><label for="notes">Observações:</label><textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($member_data['notes']); ?></textarea></div>
            <div class="graduations-section">
                <h3>Histórico de Graduações</h3>
                <div id="graduations-container">
                    <?php if(empty($graduations_data)): ?><div class="graduation-item"><input type="text" name="graduation_name[]" placeholder="Nome da Graduação"><input type="number" name="graduation_year[]" placeholder="Ano (AAAA)"><button type="button" class="remove-grad-btn" style="display:none;">Remover</button></div>
                    <?php else: foreach($graduations_data as $grad): ?><div class="graduation-item"><input type="text" name="graduation_name[]" value="<?php echo htmlspecialchars($grad['graduation_name']); ?>"><input type="number" name="graduation_year[]" value="<?php echo htmlspecialchars($grad['graduation_year']); ?>"><button type="button" class="remove-grad-btn">Remover</button></div><?php endforeach; endif; ?>
                </div>
                <button type="button" id="add-graduation-btn" class="add-grad-btn">Adicionar Graduação</button>
            </div>
            <div class="form-group"><label>Foto do Membro:</label><?php if(!empty($member_data['photo_path'])&&file_exists($member_data['photo_path'])): ?><div class="current-photo-section"><img src="<?php echo htmlspecialchars($member_data['photo_path']); ?>" alt="Foto atual" style="max-width:150px;border-radius:5px;margin-bottom:10px;"><br><label><input type="checkbox" name="remove_photo" value="1"> Marque para remover</label></div><?php endif; ?><input type="file" id="member_photo" name="member_photo" accept="image/*"></div>
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="manage_members.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
    <script>
        // Cole aqui o mesmo script da página add_member.php
    </script>
</body>
</html>
