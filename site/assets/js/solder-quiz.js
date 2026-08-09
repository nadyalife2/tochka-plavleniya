/**
 * solder-quiz.js — Тест по пайке (10 вопросов, прогресс-бар, результат)
 * Точка Плавления
 */
(function () {
  const QUESTIONS = [
    {
      q: 'Какая температура жала рекомендуется для пайки бессвинцовым припоем SAC305?',
      options: ['260–280°C', '300–340°C', '380–400°C', '200–220°C'],
      correct: 1,
    },
    {
      q: 'Что означает аббревиатура «RMA» в названии флюсов RMA-223?',
      options: ['Resin Mildly Activated', 'Rosin Maximum Activity', 'Reduced Melt Alloy', 'Reactive Metal Adhesive'],
      correct: 0,
    },
    {
      q: 'Какой припой используется для «безопасного» демонтажа BGA из-за низкой температуры плавления?',
      options: ['Sn63/Pb37', 'SAC305', 'Сплав Розе (Bi/Pb/Sn)', 'ПОС-40'],
      correct: 2,
    },
    {
      q: 'Что такое «колодцевый эффект» при пайке QFN-корпусов?',
      options: ['Перегрев центрального пятака', 'Капиллярное затекание припоя под корпус', 'Образование пустот (void) под пятаком', 'Окисление контактных площадок'],
      correct: 2,
    },
    {
      q: 'Что означает маркировка жала «K» (Knife) в системе T12?',
      options: ['Жало в форме лопаточки', 'Жало в форме скоса ножа', 'Жало с тонким остриём', 'Жало для SMD'],
      correct: 1,
    },
    {
      q: 'Зачем применяют ИПС (изопропиловый спирт) после пайки?',
      options: ['Для охлаждения платы', 'Для удаления остатков флюса', 'Для улучшения адгезии', 'Для лужения жала'],
      correct: 1,
    },
    {
      q: 'При каком условии оловянная чума разрушает изделие?',
      options: ['При длительном нагреве свыше 300°C', 'При длительном воздействии температур ниже −13°C', 'При контакте с водой', 'При окислении поверхности'],
      correct: 1,
    },
    {
      q: 'Какой припой является «бессвинцовым» по стандарту RoHS?',
      options: ['Sn63/Pb37', 'ПОС-61', 'SAC305 (Sn-Ag-Cu)', 'Сплав Розе'],
      correct: 2,
    },
    {
      q: 'Что такое «термоудар» при пайке электронных компонентов?',
      options: ['Превышение максимальной температуры хранения', 'Резкий перепад температур, приводящий к растрескиванию', 'Перегрев жала паяльника', 'Короткое замыкание'],
      correct: 1,
    },
    {
      q: 'Какова оптимальная скорость подъёма температуры для бессвинцового рефлоу-профиля?',
      options: ['Не более 1°C/сек', '1–3°C/сек', '5–8°C/сек', 'Скорость не важна'],
      correct: 1,
    },
  ];

  const quizWrap      = document.getElementById('quiz-wrap');
  const quizResult    = document.getElementById('quiz-result');
  const progressFill  = document.getElementById('quiz-progress');
  const counterEl     = document.getElementById('quiz-counter');
  const questionEl    = document.getElementById('quiz-question');
  const optionsEl     = document.getElementById('quiz-options');
  const nextBtn       = document.getElementById('quiz-next');
  const restartBtn    = document.getElementById('quiz-restart');
  const scoreEl       = document.getElementById('quiz-score');
  const scoreLabelEl  = document.getElementById('quiz-score-label');

  if (!quizWrap) return;

  let current = 0;
  let score   = 0;
  let answered = false;

  function render() {
    const q = QUESTIONS[current];
    const pct = ((current) / QUESTIONS.length) * 100;

    if (progressFill) progressFill.style.width = pct + '%';
    if (counterEl) counterEl.textContent = 'Вопрос ' + (current + 1) + ' из ' + QUESTIONS.length;
    if (questionEl) questionEl.textContent = q.q;

    optionsEl.innerHTML = '';
    answered = false;
    if (nextBtn) nextBtn.style.display = 'none';

    q.options.forEach((opt, i) => {
      const btn = document.createElement('button');
      btn.className = 'quiz-option';
      btn.textContent = opt;
      btn.addEventListener('click', () => selectOption(i, q.correct));
      optionsEl.appendChild(btn);
    });
  }

  function selectOption(selected, correct) {
    if (answered) return;
    answered = true;

    const options = optionsEl.querySelectorAll('.quiz-option');
    options.forEach((btn, i) => {
      if (i === correct) btn.classList.add('correct');
      else if (i === selected) btn.classList.add('wrong');
      btn.disabled = true;
    });

    if (selected === correct) score++;
    if (nextBtn) nextBtn.style.display = 'inline-flex';
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      current++;
      if (current >= QUESTIONS.length) {
        showResult();
      } else {
        render();
      }
    });
  }

  function showResult() {
    if (quizWrap) quizWrap.style.display = 'none';
    if (quizResult) quizResult.classList.add('visible');
    if (progressFill) progressFill.style.width = '100%';
    if (scoreEl) scoreEl.textContent = score + '/' + QUESTIONS.length;
    const pct = Math.round((score / QUESTIONS.length) * 100);
    let label = '';
    if (pct >= 90) label = '🏆 Отличный результат! Вы — мастер пайки.';
    else if (pct >= 70) label = '✅ Хороший результат. Продолжайте практиковаться!';
    else if (pct >= 50) label = '📚 Неплохо, но есть над чем поработать.';
    else label = '🔧 Читайте наши статьи — станете лучше!';
    if (scoreLabelEl) scoreLabelEl.textContent = label;
  }

  if (restartBtn) {
    restartBtn.addEventListener('click', () => {
      current = 0; score = 0;
      if (quizResult) quizResult.classList.remove('visible');
      if (quizWrap) quizWrap.style.display = '';
      render();
    });
  }

  render();
})();
