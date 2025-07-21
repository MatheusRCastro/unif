CREATE TABLE IF NOT EXISTS unif{
	id_unif PRIMARY KEY;
	data_inicio_unif data,
	data_fim_unif data,
	data_inicio_inscricao_delegado data,
	data_fim_inscricao_delegado data,
	data_inicio_inscricao_comite data,
	data_fim_inscricao_comite data,
	data_inicio_inscricao_staff data,
	data_fim_inscricao_staff data
};

CREATE TABLE IF NOT EXISTS usuario{
	cpf varchar(14) PRIMARY KEY,
	nome varchar(100),
	email varchar(100),
	restricao_alimentar varchar(100),
	alergia varchar(100),
	telefone varchar(14),
	senha varchar(20),
	instituicao varchar(100),
	adm boolean set false 
};

CREATE TABLE IF NO EXISTS delegado{
	
};

CREATE TABLE IF NOT EXISTS delegacao{
	id_delegacao serial PRIMARY KEY,
	id_unif int,
	cpf varchar(14),
	verificacao_delegacao boolean,
	FOREIGN KEY (cpf) REFERENCES usuario(cpf),
	FOREIGN KEY (id_unif) REFERENCES unif(id_unif)
	
};

CREATE TABLE IF NOT EXISTS secretario{
	cpf varchar(14) PRIMARY KEY,
	funcao varchar(30),
	id_unif INT,
	FOREIGN KEY (cpf) REFERENCES usuario(cpf),
	FOREIGN KEY (id_unif) REFERENCES unif(id_unif)
};

CREATE TABLE IF NOT EXISTS staff{
	cpf varchar(14) PRIMARY KEY,
	id_unif INT,
	justificativa varchar(500),
	inscricao_aprovada boolean set false,
	FOREIGN KEY (id_unif) REFERENCES unif(id_unif),
	FOREIGN KEY (cpf) REFERENCES usuario(cpf),
};

