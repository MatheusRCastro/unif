-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Tempo de geração: 17/12/2025 às 13:41
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `unif_db`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `comite`
--

CREATE TABLE `comite` (
  `id_comite` int(11) NOT NULL,
  `id_unif` int(11) DEFAULT NULL,
  `cpf_d1` varchar(14) NOT NULL,
  `cpf_d2` varchar(14) NOT NULL,
  `cpf_d3` varchar(14) NOT NULL,
  `tipo_comite` varchar(100) DEFAULT NULL,
  `nome_comite` varchar(100) DEFAULT NULL,
  `data_comite` date DEFAULT NULL,
  `num_delegados` int(11) DEFAULT NULL,
  `descricao_comite` varchar(1000) DEFAULT NULL,
  `status` enum('pendente','aprovado','reprovado','em_andamento') DEFAULT 'pendente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `comite`
--

INSERT INTO `comite` (`id_comite`, `id_unif`, `cpf_d1`, `cpf_d2`, `cpf_d3`, `tipo_comite`, `nome_comite`, `data_comite`, `num_delegados`, `descricao_comite`, `status`) VALUES
(2, 1, '126.465.506-18', '141.765.956-47', '136.204.356-77', 'CSNU', 'Guerra do Irã', '2025-12-11', 20, 'Busca por fins pacíficos para a atual guerra do Irâ.', 'aprovado'),
(3, 1, '135.939.406-04', '149.497.936-59', '136.204.086-02', 'ACNUR', 'Questão das Mulheres no Irã', '1990-07-19', 20, 'Debater e procurar soluções para as mulheres do Irã serem inseridas na sociedade.', 'aprovado');

-- --------------------------------------------------------

--
-- Estrutura para tabela `delegacao`
--

CREATE TABLE `delegacao` (
  `id_delegacao` int(11) NOT NULL,
  `id_unif` int(11) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `verificacao_delegacao` enum('aprovado','pendente','reprovado') DEFAULT 'pendente',
  `nome` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `delegacao`
--

INSERT INTO `delegacao` (`id_delegacao`, `id_unif`, `cpf`, `verificacao_delegacao`, `nome`) VALUES
(2, 1, '141.002.686-88', 'aprovado', 'CMBH');

-- --------------------------------------------------------

--
-- Estrutura para tabela `delegado`
--

CREATE TABLE `delegado` (
  `cpf` varchar(14) NOT NULL,
  `id_comite` int(11) DEFAULT NULL,
  `representacao` int(11) DEFAULT NULL,
  `comite_desejado` int(11) DEFAULT NULL,
  `primeira_op_representacao` int(11) DEFAULT NULL,
  `segunda_op_representacao` int(11) DEFAULT NULL,
  `terceira_op_representacao` int(11) DEFAULT NULL,
  `segunda_op_comite` int(11) DEFAULT NULL,
  `terceira_op_comite` int(11) DEFAULT NULL,
  `id_delegacao` int(11) DEFAULT NULL,
  `pdf_pagamento` longblob DEFAULT NULL COMMENT 'Arquivo PDF do comprovante de matrícula/delegação',
  `status_pagamento` enum('pendente','aprovado','reprovado') DEFAULT 'pendente' COMMENT 'Status da verificação do PDF',
  `aprovado_delegacao` enum('individual','pendente','aprovado','reprovado') DEFAULT 'individual' COMMENT 'Status da delegação: individual, pendente, aprovado, reprovado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `delegado`
--

INSERT INTO `delegado` (`cpf`, `id_comite`, `representacao`, `comite_desejado`, `primeira_op_representacao`, `segunda_op_representacao`, `terceira_op_representacao`, `segunda_op_comite`, `terceira_op_comite`, `id_delegacao`, `pdf_pagamento`, `status_pagamento`, `aprovado_delegacao`) VALUES
('112.067.546-48', 2, 11, 2, 11, NULL, NULL, 2, 2, 2, 0x255044462d312e370d0a25b5b5b5b50d0a312030206f626a0d0a3c3c2f547970652f436174616c6f672f50616765732032203020522f4c616e672870742d4252293e3e0d0a656e646f626a0d0a322030206f626a0d0a3c3c2f547970652f50616765732f436f756e7420312f4b6964735b33203020525d3e3e0d0a656e646f626a0d0a332030206f626a0d0a3c3c2f547970652f506167652f506172656e742032203020522f5265736f75726365733c3c2f466f6e743c3c2f46312034203020522f46322035203020523e3e3e3e2f436f6e74656e74732036203020522f4d65646961426f785b30203020353935203834325d3e3e0d0a656e646f626a0d0a342030206f626a0d0a3c3c2f547970652f466f6e742f537562747970652f54727565547970652f42617365466f6e742f48656c7665746963612d426f6c642f456e636f64696e672f57696e416e7369456e636f64696e673e3e0d0a656e646f626a0d0a352030206f626a0d0a3c3c2f547970652f466f6e742f537562747970652f54727565547970652f42617365466f6e742f48656c7665746963612f456e636f64696e672f57696e416e7369456e636f64696e673e3e0d0a656e646f626a0d0a362030206f626a0d0a3c3c2f46696c7465722f466c6174654465636f64652f4c656e677468203136393e3e0d0a73747265616d0d0a789c9d8e4b0ec2300c05f79cc23b409484c4474b02ad0407e809b84d1845a2928aa41b86dbe3c0522cb0f9efc9b36c4fbd35ad511d78204c1c0e0d7c6f9855df2d82b0741e3a8c3520a489d178c0e20194618edc1a5602eeb02d52a18c49425ae96ca660f0e3608dfc5f4c882a9cdf33ebad2bcc7f9e06e2284ea632f9fbc432754a183cb9a2965cc52d6e711ba9de453c3557e5d4f8ae9dfa0461ea2451cd0937c3f203e26a5d495b0d0a656e6473747265616d0d0a656e646f626a0d0a372030206f626a0d0a3c3c2f547970652f466f6e742f537562747970652f54797065302f42617365466f6e742f48656c7665746963612d426f6c642f456e636f64696e672f4964656e746974792d482f44657363656e64616e74466f6e74732037203020522f546f556e69636f64652038203020523e3e0d0a656e646f626a0d0a382030206f626a0d0a3c3c2f4c656e677468203339392f46696c7465722f466c6174654465636f64653e3e0d0a73747265616d0d0a789c5d934d6e83301086f73ec51459c6c63f61010934452a6a2f50a93d40a24886639a08c738a952ef5e1b5ab5ab99f9e6d3cc37a3d9dc566359d356d39b65f4d6a38677b3f64e3a8bd6b88ec3b4f5dea31135e61a0d02c07a3470df77ae7eb44f3f1da3c7b16d7aab9d465f3d7cbc3f3fac1d8e7dc72d9c1e682ed4576f37ecaf26513ad9e129b4adbf398f8f368b76447738493c7ed33de0b2b53c7f1d5c8e9b80d6c941ebd8e8c5d2f0d9ddf1c7b1e593d60bf6aff4717bfbca59d95ee6e176f645599d14e092d38614a22005d964d4104512a8cc5a6d40c44a089165a4044d89a010284bf333850c11979cfe46fd81bcfeead09fa8fe1345c5fec4d8c7953a3ddbe71938d7749e2f39719d689ac9b946fbbff2a7cbf4f568bfa7f1933a677afe9e6221f7093b8e86c0ab5c9c28c474eb7f0182dbbfc40d0a656e6473747265616d0d0a656e646f626a0d0a787265660d0a3020390d0a3030303030303030303020363535333520660d0a30303030303030303136203030303030206e0d0a30303030303030303839203030303030206e0d0a30303030303030313435203030303030206e0d0a30303030303030323034203030303030206e0d0a30303030303030333137203030303030206e0d0a30303030303030343034203030303030206e0d0a30303030303030363133203030303030206e0d0a30303030303030383034203030303030206e0d0a747261696c65720d0a3c3c2f53697a6520392f526f6f742031203020522f496e666f2038203020522f49445b3c35443637394546333137344439343731373742443333434531384634453630453e3c35443637394546333137344439343731373742443333434531384634453630453e5d3e3e0d0a7374617274787265660d0a3935350d0a2525454f460d0a, 'pendente', 'individual'),
('123.175.426-58', 2, 5, 2, 5, NULL, NULL, 3, 2, NULL, NULL, 'pendente', 'individual'),
('136.204.086-02', NULL, NULL, 2, 6, 1, 3, 2, 3, NULL, '', 'pendente', 'individual'),
('145.185.246-08', 2, 1, 2, 1, NULL, NULL, 2, 2, NULL, NULL, 'pendente', 'individual');

-- --------------------------------------------------------

--
-- Estrutura para tabela `diretor`
--

CREATE TABLE `diretor` (
  `id_diretor` int(11) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `id_comite` int(11) NOT NULL,
  `aprovado` tinyint(1) DEFAULT 0,
  `data_inscricao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `diretor`
--

INSERT INTO `diretor` (`id_diretor`, `cpf`, `id_comite`, `aprovado`, `data_inscricao`) VALUES
(1, '126.465.506-18', 2, 0, '2025-12-11 12:04:24'),
(2, '141.765.956-47', 2, 0, '2025-12-11 12:04:24'),
(3, '136.204.356-77', 2, 0, '2025-12-11 12:04:24'),
(4, '135.939.406-04', 3, 0, '2025-12-11 17:41:42'),
(5, '149.497.936-59', 3, 0, '2025-12-11 17:41:42'),
(6, '136.204.086-02', 3, 0, '2025-12-11 17:41:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `presenca_delegado`
--

CREATE TABLE `presenca_delegado` (
  `id_presenca` int(11) NOT NULL,
  `cpf_delegado` varchar(14) NOT NULL,
  `id_unif` int(11) NOT NULL,
  `id_comite` int(11) NOT NULL,
  `sabado_manha_1` tinyint(1) DEFAULT 0,
  `sabado_manha_2` tinyint(1) DEFAULT 0,
  `sabado_tarde_1` tinyint(1) DEFAULT 0,
  `sabado_tarde_2` tinyint(1) DEFAULT 0,
  `domingo_manha_1` tinyint(1) DEFAULT 0,
  `domingo_manha_2` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `representacao`
--

CREATE TABLE `representacao` (
  `id_representacao` int(11) NOT NULL,
  `nome_representacao` varchar(100) NOT NULL,
  `id_comite` int(11) NOT NULL,
  `id_unif` int(11) NOT NULL,
  `cpf_delegado` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CPF do delegado atribuído à esta representação.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `representacao`
--

INSERT INTO `representacao` (`id_representacao`, `nome_representacao`, `id_comite`, `id_unif`, `cpf_delegado`) VALUES
(1, 'Russia', 2, 1, NULL),
(3, 'Marrocos', 2, 1, NULL),
(4, 'China', 2, 1, NULL),
(5, 'França', 2, 1, NULL),
(6, 'Alemanha', 2, 1, '136.204.086-02'),
(7, 'EUA', 2, 1, NULL),
(8, 'Irã', 2, 1, NULL),
(9, 'Israel', 2, 1, NULL),
(10, 'Japão', 2, 1, NULL),
(11, 'Brasil', 2, 1, NULL),
(12, 'Cuba', 2, 1, NULL),
(13, 'Haiti', 2, 1, NULL),
(14, 'Irã', 3, 1, NULL),
(15, 'Russia', 3, 1, NULL),
(16, 'Israel', 3, 1, NULL),
(17, 'Marrocos', 3, 1, NULL),
(18, 'China', 3, 1, NULL),
(19, 'França', 3, 1, NULL),
(20, 'Alemanha', 3, 1, NULL),
(21, 'Holanda', 3, 1, NULL),
(22, 'Cuba', 3, 1, NULL),
(23, 'Brasil', 3, 1, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `secretario`
--

CREATE TABLE `secretario` (
  `cpf` varchar(14) NOT NULL,
  `funcao` varchar(30) DEFAULT NULL,
  `id_unif` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `staff`
--

CREATE TABLE `staff` (
  `cpf` varchar(14) NOT NULL,
  `id_unif` int(11) DEFAULT NULL,
  `justificativa` varchar(500) DEFAULT NULL,
  `status_inscricao` enum('pendente','aprovado','reprovado') DEFAULT 'pendente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `staff`
--

INSERT INTO `staff` (`cpf`, `id_unif`, `justificativa`, `status_inscricao`) VALUES
('125.952.936-30', 1, 'iapshdoa jdoiasjdhasoidhaiushdc9asyduibxnkjozsxadas', 'aprovado'),
('136.204.356-77', 1, 'Gostaria de participar como staff, pois sou bom seguindo ordens.', 'pendente');

-- --------------------------------------------------------

--
-- Estrutura para tabela `unif`
--

CREATE TABLE `unif` (
  `id_unif` int(11) NOT NULL,
  `data_inicio_unif` date DEFAULT NULL,
  `data_fim_unif` date DEFAULT NULL,
  `data_inicio_inscricao_delegado` date DEFAULT NULL,
  `data_fim_inscricao_delegado` date DEFAULT NULL,
  `data_inicio_inscricao_comite` date DEFAULT NULL,
  `data_fim_inscricao_comite` date DEFAULT NULL,
  `data_inicio_inscricao_staff` date DEFAULT NULL,
  `data_fim_inscricao_staff` date DEFAULT NULL,
  `nome` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `unif`
--

INSERT INTO `unif` (`id_unif`, `data_inicio_unif`, `data_fim_unif`, `data_inicio_inscricao_delegado`, `data_fim_inscricao_delegado`, `data_inicio_inscricao_comite`, `data_fim_inscricao_comite`, `data_inicio_inscricao_staff`, `data_fim_inscricao_staff`, `nome`) VALUES
(1, '2026-06-26', '2026-06-28', '2025-12-01', '2026-01-01', '2025-12-01', '2026-01-01', '2025-12-01', '2026-01-01', 'UNIF 2026');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario`
--

CREATE TABLE `usuario` (
  `cpf` varchar(14) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `email_instituicao` varchar(100) DEFAULT NULL,
  `restricao_alimentar` varchar(100) DEFAULT NULL,
  `alergia` varchar(100) DEFAULT NULL,
  `telefone` varchar(14) DEFAULT NULL,
  `telefone_instituicao` varchar(20) DEFAULT NULL,
  `senha` varchar(20) DEFAULT NULL,
  `instituicao` varchar(100) DEFAULT NULL,
  `adm` tinyint(1) DEFAULT 0,
  `senha_hash` varchar(255) DEFAULT NULL,
  `professor` enum('aprovado','pendente','reprovado','aluno') DEFAULT 'aluno'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuario`
--

INSERT INTO `usuario` (`cpf`, `nome`, `email`, `email_instituicao`, `restricao_alimentar`, `alergia`, `telefone`, `telefone_instituicao`, `senha`, `instituicao`, `adm`, `senha_hash`, `professor`) VALUES
('112.067.546-48', 'Leonardo Rodrigues Ferreira', 'leonardorofe12@gmail.com', NULL, 'Tradicional', '', '(31) 98547-920', NULL, 'Rofe1228', 'IFMG-OB', 0, NULL, 'aluno'),
('119.149.336-99', 'Artur de Sousa Barroso', 'arturbarroso631@gmail.com', NULL, 'Tradicional', '', '(31) 97576-326', NULL, 'artur2007', 'IFMG-OB', 0, NULL, 'aluno'),
('123.175.426-58', 'Alana Sarah Apolinário de Freitas', 'alanasafreitas@gmail.com', NULL, 'Tradicional', '', '(31) 98819-542', NULL, '@37639656', 'IFMG-OB', 0, NULL, 'aluno'),
('123.456.789-00', 'Administrador Sistema', 'admin@unif.com', NULL, NULL, NULL, '11999999999', NULL, 'admin123', 'UNIF Organização', 1, '0192023a7bbd73250516f069df18b500', 'aluno'),
('125.952.936-30', 'Cauã Victor Alves Moreira Batista', 'batistacaua973@gmail.com', NULL, 'Tradicional', '', '(31) 97128-068', NULL, 'bt2007', 'IFMG-OB', 0, NULL, 'aluno'),
('126.465.506-18', 'Shogun', 'gustavocsegheto1@gmail.com', NULL, 'Tradicional', '', '(31) 98595-570', NULL, '123123', 'IFMG-OB', 0, NULL, 'aluno'),
('130.091.226-08', 'Fernanda Xavier', 'fernanda.carmo.xavier@gmail.com', NULL, 'Tradicional', '', '(31) 97174-103', NULL, 'N@ndinha13', 'IFMG-OB', 0, NULL, 'aluno'),
('135.781.456-96', 'Arthur Cesar dos Santos ', 'cesardossantosarthur@gmail.com', NULL, 'Tradicional', '', '(31) 98532-067', NULL, 'abcdefg', 'IFMG-OB', 0, NULL, 'aluno'),
('135.939.406-04', 'Eliza Chefa', 'elizaacademico72@gmail.com', NULL, 'Tradicional', '', '(31) 99963-662', NULL, 'Informatica23', 'IFMG-OB', 0, NULL, 'aluno'),
('136.204.086-02', 'Arthur Bola', 'arthur1337art@gmail.com', NULL, 'Tradicional', '', '(31) 98321-832', NULL, 'Artc1905', 'IFMG-OB', 0, NULL, 'aluno'),
('136.204.356-77', 'Matheus Rezende de Castro', 'matheusrezendecastro@gmail.com', NULL, 'Tradicional', '', '(31) 98325-115', NULL, 'fofura190507', 'IFMG-OB', 1, NULL, 'aluno'),
('137.697.046-57', 'Nicolle Faria Vieira', 'nicollefaria24@gmail.com', NULL, 'Tradicional', '', '(31) 99548-292', NULL, 'Nicolle2910', 'IFMG-OB', 0, NULL, 'aluno'),
('139.607.106-74', 'Isadora Oliveira Ferrari', 'isadoraferrari2007@gmail.com', NULL, 'Tradicional', '', '(31) 98357-861', NULL, 'isadorao15', 'IFMG-OB', 0, NULL, 'pendente'),
('140.200.376-55', 'Gabriella Bolognani ', 'gabi.bolognanii08@gmail.com', NULL, 'Veganismo', '', '(31) 97160-914', NULL, 'Gmdi141288', 'IFMG-OB', 0, NULL, 'aluno'),
('141.002.686-88', 'Otávio Torres Alcântara Cox', 'otavio.cox15@gmail.com', 'matheusrezendecastro@gmail.com', 'Tradicional', '', '(31) 98400-546', '31984005468', 'Say my Nam3', 'IFMG-OB', 0, NULL, 'aprovado'),
('141.765.956-47', 'Ariele', 'tavaresarielle980@gmail.com', NULL, 'Tradicional', '', '(31) 98916-997', NULL, 'tavares980', 'IFMG-OB', 0, NULL, 'aluno'),
('145.185.246-08', 'Japa', 'matheusg.mendes.g5@gmail.com', NULL, 'Tradicional', '', '(31) 97192-079', NULL, 'japinha', 'IFMG-OB', 0, NULL, 'aluno'),
('149.497.936-59', 'Ricardo', 'rr0430620@gmail.com', NULL, 'Tradicional', '', '(31) 98456-343', NULL, '20070526Ric!', 'IFMG-OB', 0, NULL, 'aluno'),
('157.483.306-52', 'Lara Lopez', 'laralelopes09@gmail.com', NULL, 'Tradicional', 'viagra', '(31) 98240-559', NULL, 'cucombosta', 'IFMG-OB', 0, NULL, 'aluno'),
('158.150.026-23', 'Stanley', 'hoelzlestanley@gmail.com', NULL, 'Tradicional', 'Ambroxol', '(31) 99195-501', NULL, 'marcenes', 'IFMG-OB', 0, NULL, 'aluno'),
('162.887.816-90', 'Tom Tom', 'thommazom@gmail.com', NULL, 'intolerante a lactose', '', '(31) 99880-971', NULL, 'Gabriel*2204', 'IFMG-OB', 0, NULL, 'aluno'),
('222.333.444-55', 'Maria Santos', 'maria.santos@email.com', NULL, 'Lactose', 'Camarao', '11977776666', NULL, 'mariA456', 'Faculdade Estadual', 0, '35fdde9854048a15a1a349b379164782', 'aluno'),
('333.444.555-66', 'Pedro Oliveira', 'pedro.oliveira@email.com', NULL, NULL, 'Abelha', '11966665555', NULL, 'pedro789', 'Colégio Aplicação', 0, 'db1b9ae011ed5e6a65fb49c2d5509b2d', 'aluno'),
('444.555.666-77', 'Ana Costa', 'ana.costa@email.com', NULL, 'Vegana', 'Poeria', '11955554444', NULL, 'ana1011', 'Instituto Federal', 0, '70f9981eb5e00d6f9c63e4090b537a85', 'aluno'),
('555.666.777-88', 'Carlos Pereira', 'carlos.pereira@email.com', NULL, 'Glúten', NULL, '11944443333', NULL, 'carlos1213', 'Universidade Privada', 0, '037def801a4b4e56dcd44c880c62649b', 'aluno'),
('666.777.888-99', 'Juliana Lima', 'juliana.lima@email.com', NULL, NULL, 'Latex', '11933332222', NULL, 'juli1415', 'Escola Técnica', 0, 'e31fc749bf1a57a6d3c735f09cc544a4', 'aluno'),
('777.888.999-00', 'Rafael Souza', 'rafael.souza@email.com', NULL, 'Diabético', 'Ovo', '11922221111', NULL, 'rafa1617', 'Centro Universitário', 0, 'dfc5a3ac7ac6bca1b56b89985e940269', 'aluno'),
('888.999.000-11', 'Fernanda Rocha', 'fernanda.rocha@email.com', NULL, 'Low Carb', 'Frutos do mar', '11911110000', NULL, 'fer1819', 'Faculdade Municipal', 0, '64482ce8e7dfa39489c3bf4c15be8bec', 'aluno'),
('999.000.111-22', 'Lucas Almeida', 'lucas.almeida@email.com', NULL, NULL, 'Penicilina', '11900009999', NULL, 'luca2021', 'Universidade Publica', 0, 'f78d391c8682fe739d04876f91be2c7e', 'aluno'),
('Bia Santiago', 'Bia Santiago', 'Santhiagobeatriz21@gmail.com', NULL, 'Tradicional', 'sim, a homens', '(31) 99090-380', NULL, '12345678910', 'IFMG-OB', 0, NULL, 'aluno');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `comite`
--
ALTER TABLE `comite`
  ADD PRIMARY KEY (`id_comite`),
  ADD KEY `id_unif` (`id_unif`),
  ADD KEY `cpf_d1` (`cpf_d1`),
  ADD KEY `cpf_d2` (`cpf_d2`),
  ADD KEY `cpf_d3` (`cpf_d3`);

--
-- Índices de tabela `delegacao`
--
ALTER TABLE `delegacao`
  ADD PRIMARY KEY (`id_delegacao`,`id_unif`),
  ADD UNIQUE KEY `unique_usuario_unif` (`cpf`,`id_unif`),
  ADD KEY `cpf` (`cpf`),
  ADD KEY `id_unif` (`id_unif`);

--
-- Índices de tabela `delegado`
--
ALTER TABLE `delegado`
  ADD PRIMARY KEY (`cpf`),
  ADD KEY `id_comite` (`id_comite`),
  ADD KEY `fk_delegado_delegacao` (`id_delegacao`);

--
-- Índices de tabela `diretor`
--
ALTER TABLE `diretor`
  ADD PRIMARY KEY (`id_diretor`),
  ADD UNIQUE KEY `unique_diretor_comite` (`cpf`,`id_comite`),
  ADD KEY `fk_diretor_comite` (`id_comite`);

--
-- Índices de tabela `presenca_delegado`
--
ALTER TABLE `presenca_delegado`
  ADD PRIMARY KEY (`id_presenca`),
  ADD UNIQUE KEY `unique_presenca` (`cpf_delegado`,`id_unif`,`id_comite`);

--
-- Índices de tabela `representacao`
--
ALTER TABLE `representacao`
  ADD PRIMARY KEY (`id_representacao`),
  ADD UNIQUE KEY `uk_representacao_unif_comite` (`nome_representacao`,`id_comite`,`id_unif`),
  ADD KEY `idx_representacao_comite` (`id_comite`),
  ADD KEY `idx_representacao_unif` (`id_unif`),
  ADD KEY `idx_representacao_delegado_cpf` (`cpf_delegado`);

--
-- Índices de tabela `secretario`
--
ALTER TABLE `secretario`
  ADD PRIMARY KEY (`cpf`),
  ADD KEY `id_unif` (`id_unif`);

--
-- Índices de tabela `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`cpf`),
  ADD KEY `id_unif` (`id_unif`);

--
-- Índices de tabela `unif`
--
ALTER TABLE `unif`
  ADD PRIMARY KEY (`id_unif`);

--
-- Índices de tabela `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`cpf`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `comite`
--
ALTER TABLE `comite`
  MODIFY `id_comite` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `delegacao`
--
ALTER TABLE `delegacao`
  MODIFY `id_delegacao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `diretor`
--
ALTER TABLE `diretor`
  MODIFY `id_diretor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `presenca_delegado`
--
ALTER TABLE `presenca_delegado`
  MODIFY `id_presenca` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `representacao`
--
ALTER TABLE `representacao`
  MODIFY `id_representacao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `unif`
--
ALTER TABLE `unif`
  MODIFY `id_unif` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `comite`
--
ALTER TABLE `comite`
  ADD CONSTRAINT `comite_ibfk_1` FOREIGN KEY (`id_unif`) REFERENCES `unif` (`id_unif`),
  ADD CONSTRAINT `comite_ibfk_2` FOREIGN KEY (`cpf_d1`) REFERENCES `usuario` (`cpf`),
  ADD CONSTRAINT `comite_ibfk_3` FOREIGN KEY (`cpf_d2`) REFERENCES `usuario` (`cpf`),
  ADD CONSTRAINT `comite_ibfk_4` FOREIGN KEY (`cpf_d3`) REFERENCES `usuario` (`cpf`);

--
-- Restrições para tabelas `delegacao`
--
ALTER TABLE `delegacao`
  ADD CONSTRAINT `delegacao_ibfk_1` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`),
  ADD CONSTRAINT `delegacao_ibfk_2` FOREIGN KEY (`id_unif`) REFERENCES `unif` (`id_unif`);

--
-- Restrições para tabelas `delegado`
--
ALTER TABLE `delegado`
  ADD CONSTRAINT `delegado_ibfk_1` FOREIGN KEY (`id_comite`) REFERENCES `comite` (`id_comite`),
  ADD CONSTRAINT `delegado_ibfk_2` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`),
  ADD CONSTRAINT `fk_delegado_delegacao` FOREIGN KEY (`id_delegacao`) REFERENCES `delegacao` (`id_delegacao`) ON DELETE SET NULL;

--
-- Restrições para tabelas `diretor`
--
ALTER TABLE `diretor`
  ADD CONSTRAINT `fk_diretor_comite` FOREIGN KEY (`id_comite`) REFERENCES `comite` (`id_comite`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_diretor_usuario` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `representacao`
--
ALTER TABLE `representacao`
  ADD CONSTRAINT `fk_representacao_comite` FOREIGN KEY (`id_comite`) REFERENCES `comite` (`id_comite`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_representacao_delegado` FOREIGN KEY (`cpf_delegado`) REFERENCES `delegado` (`cpf`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_representacao_unif` FOREIGN KEY (`id_unif`) REFERENCES `unif` (`id_unif`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `secretario`
--
ALTER TABLE `secretario`
  ADD CONSTRAINT `secretario_ibfk_1` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`),
  ADD CONSTRAINT `secretario_ibfk_2` FOREIGN KEY (`id_unif`) REFERENCES `unif` (`id_unif`);

--
-- Restrições para tabelas `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`id_unif`) REFERENCES `unif` (`id_unif`),
  ADD CONSTRAINT `staff_ibfk_2` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
