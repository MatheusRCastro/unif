abrirComite = document.getElementById('abrirIncricaoComite')
avaliarComite = document.getElementById('avaliarComiteInscrito')
abrirStaff = document.getElementById('abrirIncricaoStaff')
avaliarPagamento = document.getElementById('avaliarPagamentos')
avaliarDelegacoes = document.getElementById('avaliarDelegacoesInscritas')

seta1 = document.getElementById('etapa1')
seta2 = document.getElementById('etapa2')
seta3 = document.getElementById('etapa3')
seta4 = document.getElementById('etapa4')

function conferirData(dataAtual, dataInscricao, dataStaff) {

}

function mudarCorSeta(num) {
    if (num == 1) {
        seta1.style.backgroundColor = "green";
    } else if (num == 2) {
        seta2.style.backgroundColor = "green";
    } else if (num == 3) {
        seta3.style.backgroundColor = "green";
    } else if (num == 4) {
        seta4.style.backgroundColor = "green";
    }
}
