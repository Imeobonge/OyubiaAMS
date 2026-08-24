/* OyubiaCYF form builder — dynamic question editor for admin/forms/builder.php.
   Emits inputs named fields[i][label|help_text|type|is_required] and
   fields[i][options][]. DOM order determines saved question order. */
(function () {
  'use strict';

  var TYPES = {
    short_text: 'Short text',
    paragraph: 'Paragraph',
    multiple_choice: 'Multiple choice',
    checkboxes: 'Checkboxes',
    dropdown: 'Dropdown',
    email: 'Email',
    phone: 'Phone',
    number: 'Number',
    date: 'Date',
    file_upload: 'File upload'
  };
  var HAS_OPTIONS = { multiple_choice: 1, checkboxes: 1, dropdown: 1 };

  var list = document.getElementById('fieldList');
  var addBtn = document.getElementById('addField');
  if (!list || !addBtn) return;

  var counter = 0;

  function h(tag, attrs, kids) {
    var node = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'class') node.className = attrs[k];
      else if (k === 'text') node.textContent = attrs[k];
      else node.setAttribute(k, attrs[k]);
    });
    (kids || []).forEach(function (c) { if (c) node.appendChild(c); });
    return node;
  }

  function optionRow(i, value) {
    var input = h('input', {
      type: 'text', name: 'fields[' + i + '][options][]',
      value: value || '', placeholder: 'Option text', class: 'fb-opt-input'
    });
    var del = h('button', { type: 'button', class: 'btn small ghost', text: '×', title: 'Remove option' });
    var row = h('div', { class: 'fb-opt-row' }, [input, del]);
    del.addEventListener('click', function () { row.remove(); });
    return row;
  }

  function renderField(data) {
    data = data || {};
    var i = counter++;
    var type = TYPES[data.type] ? data.type : 'short_text';

    // --- header: type select, required toggle, move + remove ---
    var typeSel = h('select', { class: 'fb-type' });
    Object.keys(TYPES).forEach(function (key) {
      var o = h('option', { value: key, text: TYPES[key] });
      if (key === type) o.selected = true;
      typeSel.appendChild(o);
    });
    typeSel.name = 'fields[' + i + '][type]';

    var reqBox = h('input', { type: 'checkbox', name: 'fields[' + i + '][is_required]', value: '1' });
    if (data.is_required) reqBox.checked = true;
    var reqLabel = h('label', { class: 'fb-req' }, [reqBox, document.createTextNode(' Required')]);

    var up = h('button', { type: 'button', class: 'btn small ghost', text: '↑', title: 'Move up' });
    var down = h('button', { type: 'button', class: 'btn small ghost', text: '↓', title: 'Move down' });
    var del = h('button', { type: 'button', class: 'btn small ghost', text: 'Remove', title: 'Remove question' });

    var header = h('div', { class: 'fb-head' }, [typeSel, reqLabel, h('span', { class: 'fb-spacer' }), up, down, del]);

    // --- label + help ---
    var label = h('input', {
      type: 'text', name: 'fields[' + i + '][label]', value: data.label || '',
      placeholder: 'Question text', class: 'fb-label'
    });
    var help = h('input', {
      type: 'text', name: 'fields[' + i + '][help_text]', value: data.help_text || '',
      placeholder: 'Help text (optional)', class: 'fb-help'
    });

    // --- options editor (choice types) ---
    var optList = h('div', { class: 'fb-opts' });
    var addOpt = h('button', { type: 'button', class: 'btn small secondary', text: '+ Add option' });
    var optWrap = h('div', { class: 'fb-opts-wrap' }, [optList, addOpt]);
    addOpt.addEventListener('click', function () { optList.appendChild(optionRow(i, '')); });

    var seeded = (data.options || []);
    if (seeded.length) seeded.forEach(function (v) { optList.appendChild(optionRow(i, v)); });

    function syncOptions() {
      if (HAS_OPTIONS[typeSel.value]) {
        optWrap.style.display = '';
        if (!optList.children.length) {
          optList.appendChild(optionRow(i, ''));
          optList.appendChild(optionRow(i, ''));
        }
      } else {
        optWrap.style.display = 'none';
      }
    }
    typeSel.addEventListener('change', syncOptions);

    var card = h('div', { class: 'fb-card' }, [header, label, help, optWrap]);

    up.addEventListener('click', function () {
      var prev = card.previousElementSibling;
      if (prev) list.insertBefore(card, prev);
    });
    down.addEventListener('click', function () {
      var next = card.nextElementSibling;
      if (next) list.insertBefore(next, card);
    });
    del.addEventListener('click', function () {
      card.remove();
      if (!list.children.length) renderField({});
    });

    list.appendChild(card);
    syncOptions();
    return card;
  }

  addBtn.addEventListener('click', function () {
    var c = renderField({});
    c.querySelector('.fb-label').focus();
  });

  // Seed existing fields (edit / error re-render), else start with one blank.
  var seed = [];
  var seedEl = document.getElementById('seedFields');
  if (seedEl) { try { seed = JSON.parse(seedEl.textContent) || []; } catch (e) { seed = []; } }
  if (seed.length) seed.forEach(renderField);
  else renderField({});

  // Guard: require at least one labelled question before submit.
  var form = document.getElementById('formBuilder');
  if (form) form.addEventListener('submit', function (e) {
    var hasOne = Array.prototype.some.call(list.querySelectorAll('.fb-label'), function (inp) {
      return inp.value.trim() !== '';
    });
    if (!hasOne) {
      e.preventDefault();
      alert('Add at least one question with text before saving.');
    }
  });
})();
