<?php
    //parãmetros de conexão com BD
    //define('HOST', 'localhost');//define o endereço do servidor (CASA)
    define('HOST', '3.150.114.68');//define  o endereço do do servidor (IFMG)
    define('USER', 'tcc_unif');; //nome do usuário
    define('PASSWORD', 'cfN610+'); //define a senha de acesso ao BD
    define('DB', 'unif_db'); //define o nome do Bando de Dados

    //criar um objeto de conexão
    $conn = new mysqli(HOST, USER, PASSWORD, DB);

    //checar a conexão
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }else{
        echo "Conexão realizada com sucesso!";
    }

    //echo "Conexão realizada com sucesso";
?>
