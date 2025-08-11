// Файл: script.js
// JS для отправки формы, маски телефона и базовой интерактивности.
// Автор: KADRA Records — шаблон

document.addEventListener('DOMContentLoaded', function() {
  // год в футере
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  // Маска для телефона через Inputmask (подключается через CDN в HTML)
  if (window.Inputmask) {
    Inputmask({"mask":"+7 (999) 999-99-99"}).mask(document.querySelectorAll('input[name="phone"]'));
  }

  // Сбор UTM (если есть) и подставить в скрытые поля
  function getParam(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name) || '';
  }
  ['utm_source','utm_medium','utm_campaign'].forEach(k=>{
    const el = document.getElementById(k);
    if(el) el.value = getParam(k);
  });

  // Перехват отправки формы: AJAX + сообщение пользователю
  const form = document.getElementById('leadForm');
  const formMessage = document.getElementById('formMessage');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (form.dataset.sending === "1") return;
      form.dataset.sending = "1";
      formMessage.textContent = 'Отправка...';

      const data = new FormData(form);
      fetch(form.action, {
        method: 'POST',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(r => r.json())
      .then(json => {
        if (json && json.success) {
          formMessage.textContent = 'Спасибо! Мы свяжемся с вами в течение часа.';
          form.reset();
        } else {
          formMessage.textContent = json && json.error ? json.error : 'Ошибка отправки. Попробуйте позже.';
        }
      })
      .catch(err => {
        console.error(err);
        formMessage.textContent = 'Ошибка сети. Попробуйте позже.';
      })
      .finally(()=> {
        form.dataset.sending = "0";
      });
    });
  }

  // Кнопки, которые заполняют поле service и прокручивают к форме
  document.querySelectorAll('[data-service]').forEach(btn=>{
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const service = this.getAttribute('data-service');
      const select = document.querySelector('select[name="service"]');
      if(select) select.value = service;
      document.querySelector('#form')?.scrollIntoView({behavior:'smooth', block:'center'});
    });
  });

  // smooth scroll для кнопок с data-scroll
  document.querySelectorAll('[data-scroll]').forEach(btn=>{
    btn.addEventListener('click', (e)=>{
      e.preventDefault();
      const target = document.querySelector(btn.dataset.scroll);
      if(target) target.scrollIntoView({behavior:'smooth', block:'center'});
    });
  });

});
