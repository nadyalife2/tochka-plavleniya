/**
 * solder-quiz.js — Интерактивный тест по пайке (10 ситуационных вопросов)
 */
const quizQuestions = [
  {
    question: "При депайке массивного разъёма термопаяльником жалом «Игла» тепло не передаётся контакту. В чём основная причина?",
    options: [
      "Температура паяльника слишком низкая (нужно поставить 480°C)",
      "Маленькое пятно контакта жала «Игла» не обеспечивает достаточный теплоперенос",
      "Флюс высох и мешает нагреву",
      "Плата слишком толстая и её нельзя паять"
    ],
    correct: 1,
    explanation: "Жало «Игла» (I/IS) имеет ничтожную теплоёмкость. Для массивных полигонов следует использовать жало типа «Микроволна» (C/BC) или «Топорик» (K)."
  },
  {
    question: "Какая температура на дисплее станции считаются оптимальной для работы со стандартным свинецсодержащим припоем ПОС-61 (Sn63/Pb37)?",
    options: [
      "220°C — 240°C",
      "280°C — 320°C (с учётом потерь на жале ~300-330°C)",
      "400°C — 450°C",
      "500°C"
    ],
    correct: 1,
    explanation: "Точка плавления ПОС-61 — 183°C. Рабочая температура жала с учётом теплоотвода текстолита — 280–330°C. 400°C быстро окисляет жало и выжигает флюс."
  },
  {
    question: "Что происходит с нетребующим отмывки (No-Clean) флюсом при высокой влажности на высокочастотной плате?",
    options: [
      "Он превращается в защитный лак",
      "Остатки флюса могут впитывать влагу и вызывать паразитное сопротивление или коррозию",
      "Ничего, он полностью испаряется",
      "Он проводит ток лучше меди"
    ],
    correct: 1,
    explanation: "Даже No-Clean флюс содержит неактивные смолы и катализаторы. Во влажной среде или цепях с высокой чувствительностью (nA / MHz) отмывка обязательна."
  },
  {
    question: "В чём главное отличие паяльных картриджей JBC C245 / T12 от обычных паяльников с раздельной керамикой?",
    options: [
      "В картридже нагреватель, термодатчик и жало объединены в монолитный узел",
      "Картриджи работают от постоянного тока 220V",
      "Картриджи сделаны из чистого серебра",
      "Картриджи не требуют использования флюса"
    ],
    correct: 0,
    explanation: "Монолитная конструкция без воздушного зазора обеспечивает обратную связь по температуре за доли секунды и мгновенный догрев под нагрузкой."
  },
  {
    question: "Зачем при монтаже BGA микросхем используется нижний подогрев (Preheater)?",
    options: [
      "Чтобы расплавить припой без фена",
      "Чтобы уменьшить градиент температур, предотвратить деформацию платы и снять теплоотвод с полигонов",
      "Чтобы сушить флюс перед посадкой",
      "Для подсветки платы снизу"
    ],
    correct: 1,
    explanation: "Преднагрев платы до 120–150°C предотвращает термошок, выгибание текстолита и позволяет посадить BGA с минимальной температурой фена."
  },
  {
    question: "Какое явление обеспечивает центрирование BGA чипа на пятаках при расплавлении шариков?",
    options: [
      "Магнитное притяжение",
      "Поверхностное натяжение расплавленного припоя",
      "Гравитационный отклик",
      "Электростатический заряд"
    ],
    correct: 1,
    explanation: "Сила поверхностного натяжения жидкого припоя буквально затягивает чип ровно на посадочные места, если пятачки чисто лужёные и покрыты флюсом."
  },
  {
    question: "Для чего применяется Сплав Розе (температура плавления ~94°C)?",
    options: [
      "Для финальной пайки силовых разъёмов",
      "Для снижения температуры плавления старого безсвинцового припоя при демонтаже сложных компонентов",
      "Для восстановления оторванных дорожек",
      "Для склеивания корпуса платы"
    ],
    correct: 1,
    explanation: "Сплав Розе подмешивают в бессвинцовые галтели, чтобы снять SMD чип без перегрева платы феном. Обязательно вымывается перед чистой сборкой!"
  },
  {
    question: "Что означает маркировка бессвинцового припоя SAC305?",
    options: [
      "Олово 96.5%, Серебро 3.0%, Медь 0.5%",
      "Свинец 30%, Сурьма 5%",
      "Кремний 30%, Алюминий 5%",
      "Сплав олова и цинка"
    ],
    correct: 0,
    explanation: "SAC305 = Sn (Sn 96.5%) + Ag (Silver 3.0%) + Cu (Copper 0.5%). Популярный промышленный бессвинцовый сплав с Tпл ~217°C."
  },
  {
    question: "Почему нельзя чистить горячее паяльное жало напильником или наждачкой?",
    options: [
      "Жало станет слишком острым",
      "Снимается защитный никелированный и железосодержащий слой, после чего медный сердечник быстро выгорает",
      "Напильник расплавится",
      "Жало потеряет намагниченность"
    ],
    correct: 1,
    explanation: "Современные жала имеют многослойное защитное покрытие (никель/хром/железо). Абразив разрушает его, и оголённая медь мгновенно растворяется в припое."
  },
  {
    question: "Какая главная опасность при перегреве паяльной пасты феном на трафарете?",
    options: [
      "Паста превращается в камень",
      "Флюс выгорает слишком быстро, припой образует отдельные мелкие шарики вместо монолитной галтели",
      "Трафарет приваривается к плате",
      "Микросхема меняет цвет"
    ],
    correct: 1,
    explanation: "Слишком быстрый обдув без фазы преднагрева выжигает флюсовую связку до расплавления шариков, приводя к шарикообразованию и соплям."
  }
];

document.addEventListener('DOMContentLoaded', () => {
  let currentIdx = 0;
  let score = 0;
  let answered = false;

  const progressFill = document.getElementById('quiz-progress');
  const counter = document.getElementById('quiz-counter');
  const questionEl = document.getElementById('quiz-question');
  const optionsEl = document.getElementById('quiz-options');
  const nextBtn = document.getElementById('quiz-next');
  const quizWrap = document.getElementById('quiz-wrap');
  const quizResult = document.getElementById('quiz-result');
  const scoreEl = document.getElementById('quiz-score');
  const scoreLabel = document.getElementById('quiz-score-label');
  const restartBtn = document.getElementById('quiz-restart');

  function renderQuestion() {
    if (!questionEl) return;
    answered = false;
    const q = quizQuestions[currentIdx];

    counter.textContent = `Вопрос ${currentIdx + 1} из ${quizQuestions.length}`;
    progressFill.style.width = `${((currentIdx) / quizQuestions.length) * 100}%`;
    questionEl.textContent = q.question;

    optionsEl.innerHTML = '';
    q.options.forEach((optText, i) => {
      const btn = document.createElement('button');
      btn.className = 'quiz-opt-btn';
      btn.innerHTML = `<span>${optText}</span>`;
      btn.addEventListener('click', () => handleAnswer(i, btn));
      optionsEl.appendChild(btn);
    });

    nextBtn.style.display = 'none';
  }

  function handleAnswer(selectedIdx, btn) {
    if (answered) return;
    answered = true;

    const q = quizQuestions[currentIdx];
    const allBtns = optionsEl.querySelectorAll('.quiz-opt-btn');

    if (selectedIdx === q.correct) {
      btn.classList.add('correct');
      btn.innerHTML += ' <span>✓</span>';
      score++;
    } else {
      btn.classList.add('wrong');
      btn.innerHTML += ' <span>✗</span>';
      allBtns[q.correct].classList.add('correct');
    }

    const exp = document.createElement('div');
    exp.className = 'quiz-explanation';
    exp.innerHTML = `<strong>Разбор:</strong> ${q.explanation}`;
    optionsEl.appendChild(exp);

    nextBtn.style.display = 'inline-flex';
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      currentIdx++;
      if (currentIdx < quizQuestions.length) {
        renderQuestion();
      } else {
        showResults();
      }
    });
  }

  function showResults() {
    quizWrap.style.display = 'none';
    quizResult.classList.add('show');
    scoreEl.textContent = `${score} / ${quizQuestions.length}`;

    let label = '';
    if (score === 10) label = '🏆 Гуру верстака! Вы знаете температуру и свойства припоев с закрытыми глазами.';
    else if (score >= 7) label = '⚡ Отличный результат! Уверенный инженер-практик.';
    else if (score >= 5) label = '🔧 Хорошая база, но есть пара пробелов в флюсах и теплоёмкости.';
    else label = '📚 Стоит подтянуть теорию и перечитать наши гайды по флюсам и температурам.';

    scoreLabel.textContent = label;
  }

  if (restartBtn) {
    restartBtn.addEventListener('click', () => {
      currentIdx = 0;
      score = 0;
      quizResult.classList.remove('show');
      quizWrap.style.display = 'block';
      renderQuestion();
    });
  }

  if (questionEl) {
    renderQuestion();
  }
});
