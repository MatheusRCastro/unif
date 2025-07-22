SET SEARCH_PATH to unif

CREATE TABLE IF NOT EXISTS unif(
	id_unif serial PRIMARY KEY,
	data_inicio_unif date,
	data_fim_unif date,
	data_inicio_inscricao_delegado date,
	data_fim_inscricao_delegado date,
	data_inicio_inscricao_comite date,
	data_fim_inscricao_comite date,
	data_inicio_inscricao_staff date,
	data_fim_inscricao_staff date
);

CREATE TABLE IF NOT EXISTS comite(
	id_comite serial PRIMARY KEY,
	id_unif int,
	cpf_d1 varchar(14) NOT NULL,
	cpf_d2 varchar(14) NOT NULL,
	cpf_d3 varchar(14) NOT NULL,
	cpf_d4 varchar(14) NOT NULL,
	tipo_comite varchar(100),
	nome_comite varchar(14),
	data_comite date,
	num_delegados int,
	descricao_comite varchar(1000),
	comite_aprovado boolean DEFAULT FALSE,
	FOREIGN KEY (id_unif) REFERENCES unif(id_unif)
);

CREATE TABLE IF NOT EXISTS usuario(
	cpf varchar(14) PRIMARY KEY,
	nome varchar(100),
	email varchar(100),
	restricao_alimentar varchar(100),
	alergia varchar(100),
	telefone varchar(14),
	senha varchar(20),
	instituicao varchar(100),
	adm boolean DEFAULT false 
);

CREATE TABLE IF NOT EXISTS delegado(
	cpf varchar(14) PRIMARY KEY,
	id_comite int,
	representacao int,
	comite_desejado int,
	primeira_op_representacao int,
	segunda_op_representacao int,
	terceira_op_representacao int,
	segunda_op_comite int,
	terceira_op_comite int,
	FOREIGN KEY (id_comite) REFERENCES comite(id_comite),
	FOREIGN KEY (cpf) REFERENCES usuario(cpf)
);

CREATE TABLE IF NOT EXISTS delegacao(
	id_delegacao serial,
	id_unif int,
	cpf varchar(14),
	verificacao_delegacao boolean,
	FOREIGN KEY (cpf) REFERENCES usuario(cpf),
	FOREIGN KEY (id_unif) REFERENCES unif(id_unif),
	PRIMARY KEY (id_delegacao,id_unif)
);

CREATE TABLE IF NOT EXISTS secretario(
	cpf varchar(14) PRIMARY KEY,
	funcao varchar(30),
	id_unif INT,
	FOREIGN KEY (cpf) REFERENCES usuario(cpf),
	FOREIGN KEY (id_unif) REFERENCES unif(id_unif)
);

CREATE TABLE IF NOT EXISTS staff(
	cpf varchar(14) PRIMARY KEY,
	id_unif INT,
	justificativa varchar(500),
	inscricao_aprovada boolean DEFAULT FALSE ,
	FOREIGN KEY (id_unif) REFERENCES unif(id_unif),
	FOREIGN KEY (cpf) REFERENCES usuario(cpf)
);