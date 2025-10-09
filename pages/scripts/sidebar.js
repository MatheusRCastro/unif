document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    
    // Exemplo: recolher/expandir ao clicar no cabeçalho
    document.querySelector('.sidebar-header').addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
    });
    
    // Ou recolher em telas pequenas
    if (window.innerWidth < 768) {
        sidebar.classList.add('collapsed');
    }
});