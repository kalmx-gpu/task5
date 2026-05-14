<?php
require_once 'config.php';


 
function saveApplication($data) {
    // Загружаем конфиг и создаём подключение PDO
    $config = require 'config.php';
    
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false 
    ]);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO applications 
            (full_name, phone, email, birth_date, gender, bio, contract_agreed)
            VALUES (:full_name, :phone, :email, :birth_date, :gender, :bio, :contract_agreed)
        ");
        $stmt->execute([
            ':full_name'       => $data['full_name'],
            ':phone'           => $data['phone'],
            ':email'           => $data['email'],
            ':birth_date'      => $data['birth_date'],
            ':gender'          => $data['gender'],
            ':bio'             => $data['bio'],
            ':contract_agreed' => 1
        ]);
        
        // Получаем ID только что вставленной записи
        $applicationId = $pdo->lastInsertId();
        
        // 2. Получаем ID выбранных языков из справочника
        if (!empty($data['languages'])) {
            $placeholders = implode(',', array_fill(0, count($data['languages']), '?'));
            $stmtLang = $pdo->prepare("
                SELECT id, name FROM programming_languages 
                WHERE name IN ($placeholders)
            ");
            $stmtLang->execute($data['languages']);
            $languages = $stmtLang->fetchAll(); 
            
            // 3. Вставляем связи в таблицу application_languages
            $stmtLink = $pdo->prepare("
                INSERT INTO application_languages (application_id, language_id)
                VALUES (?, ?)
            ");
            foreach ($languages as $lang) {
                $stmtLink->execute([$applicationId, $lang['id']]);
            }
        }
        
        $pdo->commit();
        return $applicationId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e; 
    }
}