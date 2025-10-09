// scripts/loadComponents.js
document.addEventListener('DOMContentLoaded', function() {
    // Carregar sidebar
    fetch('components/sidebar.html')
      .then(response => {
        if (!response.ok) {
          throw new Error('Sidebar não encontrada');
        }
        return response.text();
      })
      .then(data => {
        // Insere a sidebar no container
        const container = document.querySelector('.container');
        if (container) {
          container.insertAdjacentHTML('afterbegin', data);
        }
      })
      .catch(error => {
        console.error('Erro ao carregar sidebar:', error);
      });
  });