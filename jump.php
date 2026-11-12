<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>Искорка Прыгалка</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<style>
  *{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
  body{background:#87CEEB;display:flex;flex-direction:column;align-items:center;
       justify-content:center;height:100vh;font-family:sans-serif;color:#fff;
       overflow:hidden;touch-action:none;user-select:none}
  #hud{display:flex;justify-content:space-between;width:100%;max-width:360px;
       padding:8px 16px;font-size:20px;color:#333;font-weight:bold;text-shadow: 1px 1px 0 #fff;}
  #game{background:linear-gradient(#87CEEB, #e0f6ff);border-radius:10px;
        display:block;touch-action:none;max-width:100%;height:auto; box-shadow: 0 4px 10px rgba(0,0,0,0.2);}
  #overlay{position:absolute;inset:0;background:rgba(0,0,0,.7);display:none;
           flex-direction:column;align-items:center;justify-content:center;
           gap:16px;z-index:10;padding:20px;text-align:center}
  #overlay.show{display:flex}
  button{padding:12px 28px;font-size:17px;border:none;border-radius:10px;
         background:#ffaa00;color:#1a1a2e;font-weight:bold;cursor:pointer}
  button:active{transform:scale(.95)}
</style>
</head>
<body>

<div id="hud">
  <span>Высота: <b id="score">0</b></span>
</div>

<canvas id="game" width="360" height="600"></canvas>

<div id="overlay">
  <h2 id="overlayText">Игра окончена</h2>
  <p id="finalScore"></p>
  <button id="restartBtn">Играть снова</button>
</div>

<script>
// Инициализация Telegram WebApp
if (window.Telegram && Telegram.WebApp) {
  Telegram.WebApp.ready();
  Telegram.WebApp.expand();
}

const canvas = document.getElementById('game');
const ctx = canvas.getContext('2d');
const scoreEl = document.getElementById('score');
const overlay = document.getElementById('overlay');
const overlayText = document.getElementById('overlayText');
const finalScore = document.getElementById('finalScore');
const restartBtn = document.getElementById('restartBtn');

const W = canvas.width, H = canvas.height;
let player, platforms, score, running, gravity, jumpForce;

// --- НАСТРОЙКИ ---
const PLAYER_SIZE = 50; // Размер Искорки
const PLATFORM_W = 80;
const PLATFORM_H = 20;

// Загрузка картинки Искорки (УКАЖИТЕ ТУТ ВАШЕ ИМЯ ФАЙЛА)
const iskorkaImg = new Image();
iskorkaImg.src = 'iskorka1.png'; 

// Управление
const keys = { left:false, right:false };

function reset() {
  score = 0;
  running = true;
  gravity = 0.6;
  jumpForce = -12;
  
  player = {
    x: W/2 - PLAYER_SIZE/2,
    y: H - 150,
    w: PLAYER_SIZE,
    h: PLAYER_SIZE,
    vx: 0,
    vy: 0
  };

  platforms = [];
  // Стартовая платформа под игроком
  platforms.push({ x: W/2 - PLATFORM_W/2, y: H - 100, w: PLATFORM_W, h: PLATFORM_H });
  
  // Генерируем остальные платформы
  for (let i = 1; i < 10; i++) {
    platforms.push({
      x: Math.random() * (W - PLATFORM_W),
      y: H - 100 - (i * 80), // Расстояние между платформами
      w: PLATFORM_W,
      h: PLATFORM_H
    });
  }

  scoreEl.textContent = 0;
  overlay.classList.remove('show');
}

// Слушатели клавиатуры
document.addEventListener('keydown', e => {
  if (e.key === 'ArrowLeft' || e.key === 'a') keys.left = true;
  if (e.key === 'ArrowRight' || e.key === 'd') keys.right = true;
});
document.addEventListener('keyup', e => {
  if (e.key === 'ArrowLeft' || e.key === 'a') keys.left = false;
  if (e.key === 'ArrowRight' || e.key === 'd') keys.right = false;
});

// Управление касанием/мышью (движение за пальцем)
let isTouching = false;
let touchX = 0;

function handlePointerStart(e) {
  isTouching = true;
  const rect = canvas.getBoundingClientRect();
  const scaleX = canvas.width / rect.width;
  touchX = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
  touchX *= scaleX;
}
function handlePointerMove(e) {
  if (!isTouching) return;
  e.preventDefault();
  const rect = canvas.getBoundingClientRect();
  const scaleX = canvas.width / rect.width;
  touchX = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
  touchX *= scaleX;
}
function handlePointerEnd() {
  isTouching = false;
}

canvas.addEventListener('touchstart', handlePointerStart, {passive:false});
canvas.addEventListener('touchmove', handlePointerMove, {passive:false});
canvas.addEventListener('touchend', handlePointerEnd);
canvas.addEventListener('mousedown', handlePointerStart);
canvas.addEventListener('mousemove', handlePointerMove);
canvas.addEventListener('mouseup', handlePointerEnd);

// Отрисовка Искорки
function drawPlayer() {
  // Если картинка загрузилась - рисуем её
  if (iskorkaImg.complete && iskorkaImg.naturalWidth !== 0) {
    ctx.drawImage(iskorkaImg, player.x, player.y, player.w, player.h);
  } else {
    // Иначе рисуем красный кружок
    ctx.fillStyle = '#ff4444';
    ctx.beginPath();
    ctx.arc(player.x + player.w/2, player.y + player.h/2, player.w/2, 0, Math.PI*2);
    ctx.fill();
  }
}

// Отрисовка облака-платформы
function drawPlatform(p) {
  ctx.fillStyle = '#fff';
  ctx.strokeStyle = '#333';
  ctx.lineWidth = 2;
  ctx.beginPath();
  
  // Рисуем облако из нескольких кругов
  ctx.arc(p.x + 15, p.y + 10, 15, 0, Math.PI*2);
  ctx.arc(p.x + 35, p.y + 5, 18, 0, Math.PI*2);
  ctx.arc(p.x + 60, p.y + 12, 14, 0, Math.PI*2);
  ctx.rect(p.x + 15, p.y + 10, 50, 10); // Соединяем основания
  
  ctx.fill();
  // Обводка (можно убрать, если хотите без контура)
  // ctx.stroke(); 
  
  // Линия снизу для четкости
  ctx.fillStyle = '#e0e0e0';
  ctx.fillRect(p.x, p.y + p.h - 5, p.w, 5);
}

let lastTime = 0;
function loop(t) {
  const dt = Math.min(t - lastTime, 50); 
  lastTime = t;
  const speedMultiplier = dt / 16;

  if (running) {
    // --- УПРАВЛЕНИЕ ---
    // Клавиатура
    if (keys.left) player.vx = -6;
    else if (keys.right) player.vx = 6;
    else player.vx = 0;

    // Тач (движение к пальцу)
    if (isTouching) {
      const diff = (touchX - (player.x + player.w/2));
      if (Math.abs(diff) > 5) {
        player.vx = Math.sign(diff) * 6;
      } else {
        player.vx = 0;
      }
    }

    // Физика игрока
    player.x += player.vx * speedMultiplier;
    
    // Столкновение со стенами
    if (player.x < 0) { player.x = 0; player.vx = 0; }
    if (player.x + player.w > W) { player.x = W - player.w; player.vx = 0; }

    player.vy += gravity * speedMultiplier;
    player.y += player.vy * speedMultiplier;

    // --- ПЛАТФОРМЫ ---
    for (let i = 0; i < platforms.length; i++) {
      const p = platforms[i];
      
      // Проверка, стоит ли игрок на платформе (только если падает)
      if (player.vy > 0 && 
          player.y + player.h >= p.y && 
          player.y + player.h <= p.y + p.h + 10 &&
          player.x + player.w > p.x + 5 && 
          player.x < p.x + p.w - 5) {
        
        player.vy = jumpForce;
      }
    }

    // Если игрок поднимается выше середины экрана
    if (player.y < H / 2) {
      const dy = H / 2 - player.y;
      player.y = H / 2;
      
      // Двигаем все платформы вниз
      platforms.forEach(p => p.y += dy);
      
      // Счетчик
      if (Math.floor(dy) > 0) {
        score += Math.floor(dy / 10);
        scoreEl.textContent = score;
      }
    }

    // Генерация новых платформ
    platforms.forEach(p => {
      if (p.y > H) {
        p.y = platforms[0].y - 80 - Math.random() * 40;
        p.x = Math.random() * (W - p.w);
      }
    });

    // --- ПРОИГРЫШ ---
    if (player.y > H) {
      running = false;
      overlayText.textContent = 'Искорка упала! 😢';
      finalScore.textContent = 'Высота: ' + score;
      overlay.classList.add('show');
    }
  }

  // --- ОТРИСОВКА ---
  ctx.clearRect(0, 0, W, H);
  
  // Фон (градиент уже задан в CSS, но можно добавить пушистые облака на фоне)
  // ctx.fillStyle = '#87CEEB';
  // ctx.fillRect(0, 0, W, H);
  
  platforms.forEach(drawPlatform);
  drawPlayer();

  requestAnimationFrame(loop);
}

restartBtn.addEventListener('click', reset);
reset();
requestAnimationFrame(loop);
</script>
</body>
</html>
