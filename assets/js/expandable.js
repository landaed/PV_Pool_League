document.querySelectorAll('.card').forEach(section => {
    section.addEventListener('click', function() {
      if (this.classList.contains('expanded')) {
        this.classList.remove('expanded');
      } else {
        // Collapse all cards
        document.querySelectorAll('.card.expanded').forEach(sec => {
          sec.classList.remove('expanded');
        });
  
        // Expand the clicked card
        this.classList.add('expanded');
      }
    });
  });
  