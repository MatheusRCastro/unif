-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Tempo de geração: 08/12/2025 às 12:22
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
  `cpf_d4` varchar(14) NOT NULL,
  `tipo_comite` varchar(100) DEFAULT NULL,
  `nome_comite` varchar(100) DEFAULT NULL,
  `data_comite` date DEFAULT NULL,
  `num_delegados` int(11) DEFAULT NULL,
  `descricao_comite` varchar(1000) DEFAULT NULL,
  `comite_aprovado` tinyint(1) DEFAULT 0,
  `representacao` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `delegacao`
--

CREATE TABLE `delegacao` (
  `id_delegacao` int(11) NOT NULL,
  `id_unif` int(11) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `verificacao_delegacao` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `terceira_op_comite` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `inscricao_aprovada` tinyint(1) DEFAULT 0,
  `aprovado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `data_fim_inscricao_staff` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario`
--

CREATE TABLE `usuario` (
  `cpf` varchar(14) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `restricao_alimentar` varchar(100) DEFAULT NULL,
  `alergia` varchar(100) DEFAULT NULL,
  `telefone` varchar(14) DEFAULT NULL,
  `senha` varchar(20) DEFAULT NULL,
  `instituicao` varchar(100) DEFAULT NULL,
  `adm` tinyint(1) DEFAULT 0,
  `senha_hash` varchar(255) DEFAULT NULL,
  `professor` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuario`
--

INSERT INTO `usuario` (`cpf`, `nome`, `email`, `restricao_alimentar`, `alergia`, `telefone`, `senha`, `instituicao`, `adm`, `senha_hash`, `professor`) VALUES
('111.222.333-44', 'João Silva', 'joao.silva@email.com', 'Vegetariano', 'Amendoim', '11988887777', 'senha123', 'Universidade Federal', 0, 'e7d80ffeefa212b7c5c55700e4f7193e', 0),
('123.456.789-00', 'Administrador Sistema', 'admin@unif.com', NULL, NULL, '11999999999', 'admin123', 'UNIF Organização', 1, '0192023a7bbd73250516f069df18b500', 0),
('136.204.356-77', 'Usuário 136.2', 'matheusrezendecastro@gmail.com', 'Tradicional', '', '(31) 98325-115', 'fofura190507', 'IFMG - Ouro Branco', 1, NULL, 0),
('222.333.444-55', 'Maria Santos', 'maria.santos@email.com', 'Lactose', 'Camarao', '11977776666', 'mariA456', 'Faculdade Estadual', 0, '35fdde9854048a15a1a349b379164782', 0),
('333.444.555-66', 'Pedro Oliveira', 'pedro.oliveira@email.com', NULL, 'Abelha', '11966665555', 'pedro789', 'Colégio Aplicação', 0, 'db1b9ae011ed5e6a65fb49c2d5509b2d', 0),
('444.555.666-77', 'Ana Costa', 'ana.costa@email.com', 'Vegana', 'Poeria', '11955554444', 'ana1011', 'Instituto Federal', 0, '70f9981eb5e00d6f9c63e4090b537a85', 0),
('555.666.777-88', 'Carlos Pereira', 'carlos.pereira@email.com', 'Glúten', NULL, '11944443333', 'carlos1213', 'Universidade Privada', 0, '037def801a4b4e56dcd44c880c62649b', 0),
('666.777.888-99', 'Juliana Lima', 'juliana.lima@email.com', NULL, 'Latex', '11933332222', 'juli1415', 'Escola Técnica', 0, 'e31fc749bf1a57a6d3c735f09cc544a4', 0),
('777.888.999-00', 'Rafael Souza', 'rafael.souza@email.com', 'Diabético', 'Ovo', '11922221111', 'rafa1617', 'Centro Universitário', 0, 'dfc5a3ac7ac6bca1b56b89985e940269', 0),
('888.999.000-11', 'Fernanda Rocha', 'fernanda.rocha@email.com', 'Low Carb', 'Frutos do mar', '11911110000', 'fer1819', 'Faculdade Municipal', 0, '64482ce8e7dfa39489c3bf4c15be8bec', 0),
('999.000.111-22', 'Lucas Almeida', 'lucas.almeida@email.com', NULL, 'Penicilina', '11900009999', 'luca2021', 'Universidade Publica', 0, 'f78d391c8682fe739d04876f91be2c7e', 0);

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
  ADD KEY `cpf_d3` (`cpf_d3`),
  ADD KEY `cpf_d4` (`cpf_d4`);

--
-- Índices de tabela `delegacao`
--
ALTER TABLE `delegacao`
  ADD PRIMARY KEY (`id_delegacao`,`id_unif`),
  ADD KEY `cpf` (`cpf`),
  ADD KEY `id_unif` (`id_unif`);

--
-- Índices de tabela `delegado`
--
ALTER TABLE `delegado`
  ADD PRIMARY KEY (`cpf`),
  ADD KEY `id_comite` (`id_comite`);

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
  MODIFY `id_comite` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `delegacao`
--
ALTER TABLE `delegacao`
  MODIFY `id_delegacao` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `diretor`
--
ALTER TABLE `diretor`
  MODIFY `id_diretor` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `presenca_delegado`
--
ALTER TABLE `presenca_delegado`
  MODIFY `id_presenca` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `unif`
--
ALTER TABLE `unif`
  MODIFY `id_unif` int(11) NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `comite_ibfk_4` FOREIGN KEY (`cpf_d3`) REFERENCES `usuario` (`cpf`),
  ADD CONSTRAINT `comite_ibfk_5` FOREIGN KEY (`cpf_d4`) REFERENCES `usuario` (`cpf`);

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
  ADD CONSTRAINT `delegado_ibfk_2` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`);

--
-- Restrições para tabelas `diretor`
--
ALTER TABLE `diretor`
  ADD CONSTRAINT `fk_diretor_comite` FOREIGN KEY (`id_comite`) REFERENCES `comite` (`id_comite`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_diretor_usuario` FOREIGN KEY (`cpf`) REFERENCES `usuario` (`cpf`) ON DELETE CASCADE ON UPDATE CASCADE;

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
