(() => {
  const toggle = document.querySelector('[data-toggle-nav]');
  if (toggle) {
    toggle.addEventListener('click', () => document.body.classList.toggle('nav-open'));
  }

  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) {
        e.preventDefault();
      }
    });
  });

  document.querySelectorAll('.alert').forEach((el) => {
    setTimeout(() => { el.style.opacity = '0'; }, 5000);
  });

  const path = location.pathname;
  document.querySelectorAll('.sidebar nav a').forEach((a) => {
    if (path.endsWith(a.getAttribute('href').split('/').pop())) {
      a.classList.add('active');
    }
  });

  document.querySelectorAll('[data-qr]').forEach((el) => {
    if (window.QRCode) {
      new QRCode(el, {
        text: el.getAttribute('data-qr'),
        width: 180,
        height: 180,
        colorDark: '#111111',
        colorLight: '#ffffff',
      });
    }
  });

  document.querySelectorAll('form[data-validate]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      const required = form.querySelectorAll('[required]');
      for (const field of required) {
        if (!String(field.value || '').trim()) {
          e.preventDefault();
          field.focus();
          alert('Please fill in all required fields.');
          return;
        }
      }
      const email = form.querySelector('input[type=email]');
      if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        e.preventDefault();
        alert('Enter a valid email address.');
      }
    });
  });

  window.voltCharts = (id, type, labels, data, extra = {}) => {
    const canvas = document.getElementById(id);
    if (!canvas || !window.Chart) return;
    new Chart(canvas, {
      type,
      data: {
        labels,
        datasets: [{
          label: extra.label || '',
          data,
          borderColor: extra.color || '#d6ff3e',
          backgroundColor: extra.fill || 'rgba(214,255,62,.25)',
          borderWidth: 2,
          tension: .35,
          fill: type !== 'line' ? true : extra.area !== false,
        }],
      },
      options: {
        plugins: { legend: { display: !!extra.label, labels: { color: '#c9d2dc' } } },
        scales: type === 'doughnut' ? {} : {
          x: { ticks: { color: '#93a0ae' }, grid: { color: 'rgba(255,255,255,.05)' } },
          y: { ticks: { color: '#93a0ae' }, grid: { color: 'rgba(255,255,255,.05)' } },
        },
      },
    });
  };

  window.voltPrint = () => window.print();

  const renewal = document.querySelector('[data-renewal-expiry]');
  if (renewal) {
    const expiry = Number(renewal.getAttribute('data-renewal-expiry') || 0) * 1000;
    const label = renewal.querySelector('[data-renewal-left]');
    const tick = () => {
      if (!label || !expiry) return;
      const left = expiry - Date.now();
      if (left <= 0) {
        label.textContent = 'Due now';
        return;
      }
      const days = Math.floor(left / 86400000);
      const hours = Math.floor((left % 86400000) / 3600000);
      const minutes = Math.floor((left % 3600000) / 60000);
      label.textContent = days + ' days ' + hours + ' hours ' + minutes + ' minutes remaining';
    };
    tick();
    setInterval(tick, 30000);
  }
})();
