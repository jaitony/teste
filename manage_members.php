<?php
// manage_members.php - VERSÃO COM CSS CORRIGIDO PARA OS BOTÕES

session_start();
ini_set('display_errors', 1); error_reporting(E_ALL);
include_once("conexao.php");

$message = $_SESSION['message'] ?? "";
$error_type = $_SESSION['message_type'] ?? "success";
unset($_SESSION['message']);
unset($_SESSION['message_type']);

$role = $_SESSION['role'] ?? 'user';
if ($role !== 'admin' && $role !== 'super_admin') {
    $_SESSION['message'] = "Você não tem permissão para gerenciar membros.";
    $_SESSION['message_type'] = "error";
    header("Location: dashboard.php");
    exit;
}

$loggedin_member_id = $_SESSION['member_id'] ?? null;

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $member_id_to_delete = (int)$_GET['id'];
    $conexao->begin_transaction();
    try {
        $stmt_graduations = $conexao->prepare("DELETE FROM graduations_history WHERE member_id = ?");
        $stmt_graduations->bind_param("i", $member_id_to_delete);
        $stmt_graduations->execute();
        $photo_path_query = $conexao->prepare("SELECT photo_path FROM capoeira_members WHERE id = ?");
        $photo_path_query->bind_param("i", $member_id_to_delete);
        $photo_path_query->execute();
        $photo_result = $photo_path_query->get_result();
        $member_data_for_photo = $photo_result->fetch_assoc();
        if ($member_data_for_photo && !empty($member_data_for_photo['photo_path']) && file_exists($member_data_for_photo['photo_path'])) {
            unlink($member_data_for_photo['photo_path']);
        }
        $stmt_member = $conexao->prepare("DELETE FROM capoeira_members WHERE id = ?");
        $stmt_member->bind_param("i", $member_id_to_delete);
        $stmt_member->execute();
        $conexao->commit();
        $message = "Membro excluído com sucesso!";
        $error_type = "success";
    } catch (Exception $e) {
        $conexao->rollback();
        $message = "Erro ao excluir membro: " . $e->getMessage();
        $error_type = "error";
    }
}

$members = [];
try {
    $base_query = "SELECT m.*, COALESCE(p.nickname, p.full_name) AS parent_name, tl.name AS training_location_name 
                   FROM capoeira_members m 
                   LEFT JOIN capoeira_members p ON m.parent_id = p.id 
                   LEFT JOIN training_locations tl ON m.training_location_id = tl.id";

    if (($role === 'admin' || $role === 'super_admin') && empty($loggedin_member_id)) {
        $sql = $base_query . " ORDER BY m.full_name ASC";
        $result = $conexao->query($sql);
    } else {
        $sql_ids = "WITH RECURSIVE d AS (SELECT id FROM capoeira_members WHERE id=? UNION ALL SELECT m.id FROM capoeira_members m JOIN d ON m.parent_id=d.id) SELECT id FROM d;";
        $stmt_ids = $conexao->prepare($sql_ids);
        $stmt_ids->bind_param("i", $loggedin_member_id);
        $stmt_ids->execute();
        $result_ids = $stmt_ids->get_result();
        $allowed_ids = [];
        while($row = $result_ids->fetch_assoc()) { $allowed_ids[] = $row['id']; }
        if (!empty($allowed_ids)) {
            $id_placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
            $sql = $base_query . " WHERE m.id IN ($id_placeholders) ORDER BY m.full_name ASC";
            $stmt = $conexao->prepare($sql);
            $types = str_repeat('i', count($allowed_ids));
            $stmt->bind_param($types, ...$allowed_ids);
            $stmt->execute();
            $result = $stmt->get_result();
        } else { $result = false; }
    }
    if ($result) { while ($row = $result->fetch_assoc()) { $members[] = $row; } }
} catch (mysqli_sql_exception $e) { $message = "Erro de banco de dados: " . $e->getMessage(); $error_type = "error"; }
$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Membros</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background-color: #f0f2f5; margin: 0; padding: 2rem; }
        .container { background-color: white; padding: 2.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 1400px; margin: auto; }
        .header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; }
        .header h1 { margin: 0; color: #333; }
        .btn { padding: 10px 15px; text-decoration: none; color: white; border-radius: 5px; font-weight: 500; }
        .btn-success { background-color: #28a745; }
        .btn-secondary { background-color: #6c757d; }
        .table-wrapper { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #dee2e6; }
        .table th { background-color: #f8f9fa; }
        
        /* ===== INÍCIO DA CORREÇÃO ===== */
        .table td.actions {
            white-space: nowrap; /* Impede que os botões quebrem a linha */
            width: 1%; /* Faz a coluna ter a largura mínima necessária */
        }
        .actions a {
            display: inline-block; /* Garante o alinhamento correto */
            margin-right: 8px;
        }
        .actions a:last-child {
            margin-right: 0;
        }
        /* ===== FIM DA CORREÇÃO ===== */

        .btn-sm { font-size: 0.8rem; padding: 5px 10px; }
        .btn-primary { background-color: #007bff; }
        .btn-danger { background-color: #dc3545; }
        .btn-info { background-color: #17a2b8; }
        .member-photo { width: 50px; height: 50px; object-fit: cover; border-radius: 50%; }
        .corda-image { width: 40px; height: 40px; object-fit: contain; vertical-align: middle; }
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gerenciar Membros do Grupo</h1>
            <div>
                <a href="add_member.php" class="btn btn-success">Adicionar Novo Membro</a>
                <a href="dashboard.php" class="btn btn-secondary">Voltar ao Dashboard</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo ($error_type === 'success') ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Apelido</th>
                        <th>Nome Completo</th>
                        <th>Graduação/Cargo</th>
                        <th>Mestre/Professor</th>
                        <th>Grad. Atual</th>
                        <th>Local de Treino</th>
                        <th>Status</th>
                        <th class="actions">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr><td colspan="9" style="text-align:center; padding: 2rem;">Nenhum membro encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach($members as $member): ?>
                        <tr>
                            <td><img src="<?php echo (!empty($member['photo_path']) && file_exists($member['photo_path'])) ? htmlspecialchars($member['photo_path']) : 'img/default_profile.png'; ?>" alt="Foto" class="member-photo"></td>
                            <td><?php echo htmlspecialchars($member['nickname']); ?></td>
                            <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['rank']); ?></td>
                            <td><?php echo htmlspecialchars($member['parent_name'] ?? 'N/A'); ?></td>
                            <td><?php if (!empty($member['current_cord_image']) && file_exists($member['current_cord_image'])): ?><img src="<?php echo htmlspecialchars($member['current_cord_image']); ?>" alt="Corda" class="corda-image"><?php else: ?>-<?php endif; ?></td>
                            <td><?php echo htmlspecialchars($member['training_location_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($member['status'])); ?></td>
                            <td class="actions">
                                <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                <a href="member_certificate.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-info" target="_blank">Ficha</a>
                                <a href="manage_members.php?action=delete&id=<?php echo $member['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza? Esta ação não pode ser desfeita.');">Deletar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
