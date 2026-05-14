<?php
function saveApplication($data, $userId, $pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO applications 
        (user_id, full_name, phone, email, birth_date, gender, bio, contract_agreed)
        VALUES (:user_id, :full_name, :phone, :email, :birth_date, :gender, :bio, :contract_agreed)
    ");
    $stmt->execute([
        ':user_id'       => $userId,
        ':full_name'     => $data['full_name'],
        ':phone'         => $data['phone'],
        ':email'         => $data['email'],
        ':birth_date'    => $data['birth_date'],
        ':gender'        => $data['gender'],
        ':bio'           => $data['bio'],
        ':contract_agreed' => 1
    ]);
    $applicationId = $pdo->lastInsertId();

    if (!empty($data['languages'])) {
        $placeholders = implode(',', array_fill(0, count($data['languages']), '?'));
        $stmtLang = $pdo->prepare("SELECT id, name FROM programming_languages WHERE name IN ($placeholders)");
        $stmtLang->execute($data['languages']);
        $languages = $stmtLang->fetchAll(PDO::FETCH_ASSOC);
        $stmtLink = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
        foreach ($languages as $lang) {
            $stmtLink->execute([$applicationId, $lang['id']]);
        }
    }
    return $applicationId;
}

function updateApplication($data, $userId, $pdo) {
    $stmt = $pdo->prepare("
        UPDATE applications SET
            full_name = :full_name,
            phone = :phone,
            email = :email,
            birth_date = :birth_date,
            gender = :gender,
            bio = :bio,
            contract_agreed = 1
        WHERE user_id = :user_id
    ");
    $stmt->execute([
        ':full_name'     => $data['full_name'],
        ':phone'         => $data['phone'],
        ':email'         => $data['email'],
        ':birth_date'    => $data['birth_date'],
        ':gender'        => $data['gender'],
        ':bio'           => $data['bio'],
        ':user_id'       => $userId
    ]);

    $stmtDel = $pdo->prepare("
        DELETE FROM application_languages 
        WHERE application_id = (SELECT id FROM applications WHERE user_id = ?)
    ");
    $stmtDel->execute([$userId]);

    if (!empty($data['languages'])) {
        $stmtAppId = $pdo->prepare("SELECT id FROM applications WHERE user_id = ?");
        $stmtAppId->execute([$userId]);
        $appId = $stmtAppId->fetchColumn();

        $placeholders = implode(',', array_fill(0, count($data['languages']), '?'));
        $stmtLang = $pdo->prepare("SELECT id FROM programming_languages WHERE name IN ($placeholders)");
        $stmtLang->execute($data['languages']);
        $langIds = $stmtLang->fetchAll(PDO::FETCH_COLUMN);
        $stmtLink = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
        foreach ($langIds as $langId) {
            $stmtLink->execute([$appId, $langId]);
        }
    }
}