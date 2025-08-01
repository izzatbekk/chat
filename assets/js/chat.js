// Global variables
const chatBox = document.getElementById('chat-box');
const form = document.getElementById('chat-form');
const input = document.getElementById('message');
const replyToInput = document.getElementById('reply-to');
const replyPreview = document.getElementById('reply-preview');
const scrollBtn = document.getElementById("scrollToBottomBtn");

let socket;

// Utility functions
function escapeHTML(str) {
  if (!str) return '';
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/\n/g, '<br>');
}

function formatTime(datetime) {
  const d = new Date(datetime);
  return `${d.getDate().toString().padStart(2, '0')}.${(d.getMonth() + 1).toString().padStart(2, '0')}.${d.getFullYear().toString().slice(-2)} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
}

// Connection indicator functions
function showConnecting() {
  const el = document.getElementById("connecting-indicator");
  if (el) el.style.display = "block";
}

function hideConnecting() {
  const el = document.getElementById("connecting-indicator");
  if (el) el.style.display = "none";
}

// WebSocket functions
function createSocket() {
    socket = new WebSocket('wss://' + location.hostname + ':2346/?token=' + token);
    
    showConnecting();

    socket.onopen = () => {
        console.log("✅ WebSocket ulanish muvaffaqiyatli.");
        hideConnecting();
    };

    socket.onmessage = function(event) {
        const data = JSON.parse(event.data);
        
         if (data.type === 'online_users') {
            document.getElementById('online-count').innerText = data.count;
            renderOnlineUsers(data.users); // Modalda avtomatik ko‘rsatish (modal ochilmasa ham)
        }

        
        handleMessage(data);
    };

    socket.onclose = function() {
        console.warn("⚠️ WebSocket uzilib qoldi. 3 soniyadan so'ng qayta ulanadi...");
        showConnecting();
        setTimeout(createSocket, 3000);
    };

    socket.onerror = function(err) {
        console.error("❌ WebSocket xatolik:", err.message);
        socket.close();
    };
}

// Message handling functions
function addMessage(msg) {
    const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 200;
    
    const wrapper = document.createElement('div');
    wrapper.className = msg.user_id == userId
     ? 'd-flex justify-content-end mb-2'
     : 'd-flex align-items-end mb-2';
    wrapper.setAttribute('data-id', msg.id);
    
    if (msg.user_id != userId) {
      const img = document.createElement('img');
      img.src = msg.profile_image;
      img.alt = 'Avatar';
      img.width = 40;
      img.height = 40;
      img.style.borderRadius = '50%';
      img.style.objectFit = 'cover';
      img.style.border = '1px solid #ccc';
      img.style.marginRight = '8px';
      img.style.marginBottom = '2.5px';
      img.style.flexShrink = '0';
      img.style.cursor = 'pointer';
      img.onclick = () => showUserProfilePopup(msg);
      wrapper.appendChild(img);
    }

    const div = document.createElement('div');
    div.className = 'msg ' + (msg.user_id == userId ? 'me' : 'other');
    div.setAttribute('data-id', msg.id);

    let verifiedMark = '';
    let roleBadge = '';

    if (msg.role === 'creator') {
      verifiedMark = `<i class="bi bi-patch-check-fill text-primary verified-icon" title="Creator"></i>`;
      roleBadge = `<small class="text-muted">creator</small>`;
    } else if (msg.role === 'admin') {
      verifiedMark = `<i class="bi bi-star-fill text-warning verified-icon" title="Admin"></i>`;
      roleBadge = `<small class="text-muted">admin</small>`;
    }

    let content = `
      <div class="d-flex justify-content-between align-items-center mb-1">
        <div><strong>${escapeHTML(msg.username)} ${verifiedMark}</strong></div>
        <div>${roleBadge}</div>
      </div>
    `;

    if (msg.reply_text) {
      const [replyUser, replyMessage, replyRole] = msg.reply_text.split(':').map(s => s.trim());
      const replyBg = msg.user_id == userId ? '#D4F5B8' : '#f1f9ff';

      let replyVerified = '';
      if (replyRole === 'creator') {
        replyVerified = `<i class="bi bi-patch-check-fill text-primary verified-icon" title="Creator"></i>`;
      } else if (replyRole === 'admin') {
        replyVerified = `<i class="bi bi-star-fill text-warning verified-icon" title="Admin"></i>`;
      }

      content += `<div class="ps-2 text-muted mb-1 reply-highlight" data-reply-id="${msg.reply_to}" style="cursor:pointer; font-size: 13px; border-left: 2px solid #b8b8b8; background: ${replyBg}; border-radius: 6px; padding: 6px 10px; margin-top: 2px;" onclick="scrollToMessage(${msg.reply_to})">
      <span style="color: #353535e3;"><strong>${replyUser} ${replyVerified}</strong></span><br>
      <span style="color: #212529 !important;">${escapeHTML(replyMessage)}</span>
      </div>`;
    }

    content += `${escapeHTML(msg.message)}`;
    content += `<small>${formatTime(msg.created_at)} <span class="msg-actions">`;
    content += `<button onclick="replyTo(${msg.id}, \`${msg.message}\`, \`${msg.username}\`)" class="btn btn-link">Javob yozish</button>`;

    if (msg.user_id == userId || role === 'creator' || role === 'admin' && msg.username !== 'admin') {
      content += `<button onclick="deleteMsg(${msg.id})" class="btn btn-link text-danger">O'chirish</button>`;
    }
    
    if (msg.user_id != userId && msg.username !== 'admin' && role === 'creator') {
        content += `<button onclick="blockUser(${msg.user_id})" class="btn btn-link text-danger">Block</button>`;
    }

    content += `</span></small>`;
    div.innerHTML = content;
    wrapper.appendChild(div);
    chatBox.appendChild(wrapper);

    if (isAtBottom) {
      chatBox.scrollTop = chatBox.scrollHeight;
    }
}

function handleMessage(data) {
    if (data.type === 'blocked') {
        alert("⛔ Siz admin tomonidan bloklandingiz.");
        window.location.href = 'logout.php';
        return;
    }
    
    if (data.type === 'init') {
        const user = data.user;
        setupProfileUI(user);
        chatBox.innerHTML = '';
        data.messages.forEach(addMessage);
    } else if (data.type === 'new') {
        addMessage(data.message);
    } else if (data.type === 'delete') {
    const wrapper = chatBox.querySelector(`div[data-id="${data.id}"]`);
    if (wrapper) wrapper.remove();
    }

}

// UI Setup functions
function setupProfileUI(user) {
    const profileTitle = document.getElementById('profile-info');
    if (profileTitle) {
        profileTitle.innerHTML = `
            <div class="profile-img-container" onclick="document.getElementById('add-icon').style.display = 'block'">
                <img id="profile-img" src="${user.profile_image}" alt="profile" width="40" height="40"
                     class="rounded-circle me-2"
                     onerror="this.src='/chat/profile_image/default.jpg'">
                <div id="add-icon" class="add-icon" onclick="event.stopPropagation(); document.getElementById('upload-input').click();">+</div>
            </div>
            <span class="ms-2"><strong>${user.username}</strong></span>
             <form id="upload-form" method="POST" enctype="multipart/form-data" style="display:none;">
             <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="file" name="profile_image" id="upload-input">
            </form>
        `;
        console.log("CSRF token:", csrfToken);
        setupFileUpload();
    }

    const welcomeEl = document.getElementById('profile-name');
    if (welcomeEl) {
        welcomeEl.innerHTML = `
            <img src="${user.profile_image}" alt="Profil" width="45" height="45"
                 class="rounded-circle" style="object-fit: cover;" 
                 onerror="this.src='/profile_image/default.jpg'">
            <strong style="cursor: pointer; font-size: 20px;">${user.username}</strong>
        `;
    }
}

function setupFileUpload() {
    const input = document.getElementById('upload-input');
    if (!input) return;

    input.addEventListener('change', function () {
        const file = input.files[0];
        if (!file) return;

        const maxSize = 3 * 1024 * 1024;
        const fileName = file.name.toLowerCase();

        const isJpg = fileName.endsWith('.jpg');
        const isGifAllowed = (role === 'admin' || role === 'creator') && fileName.endsWith('.gif');

        if (!(isJpg || isGifAllowed)) {
            alert("Faqat .jpg formatidagi rasm yuklashingiz mumkin. Error.");
            resetInput();
            return;
        }

        if (file.size > maxSize) {
            alert("Rasm hajmi 3 MB dan oshmasligi kerak.");
            resetInput();
            return;
        }

        input.form.submit();
    });

    function resetInput() {
        const form = document.getElementById('upload-form');
        const oldInput = document.getElementById('upload-input');
        const newInput = document.createElement('input');
        newInput.type = "file";
        newInput.name = "profile_image";
        newInput.id = "upload-input";
        if (role === 'admin' || role === 'creator') {
           newInput.accept = "image/jpg, image/gif";
        } else {
           newInput.accept = "image/jpg";
        }
        newInput.style.display = "none";

        form.replaceChild(newInput, oldInput);
        setupFileUpload(); // qayta listener biriktirish
    }
}

// Chat interaction functions
function replyTo(id, text, user) {
    replyToInput.value = id;

    // Matndagi \n ni olib tashlaymiz va uzunligini cheklaymiz (faqat ko‘rinishda)
    const plainText = text.replace(/\n/g, ' ');
    const displayText = plainText.length > 100 ? plainText.slice(0, 100) + '...' : plainText;

    replyPreview.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
          <div title="${escapeHTML(plainText)}"
               style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 75%;">
            <b>${escapeHTML(user)}</b>: ${escapeHTML(displayText)}
          </div>
          <button class="btn btn-sm btn-link text-danger" onclick="cancelReply()">Bekor qilish</button>
        </div>`;
    replyPreview.style.display = 'block';
}

function cancelReply() {
    replyToInput.value = 0;
    replyPreview.style.display = 'none';
}

function deleteMsg(id) {
    if (confirm("Xabarni o'chirishni istaysizmi?")) {
        socket.send(JSON.stringify({type: 'delete', id: id}));
    }
}

function blockUser(userIdToBlock) {
    if (confirm("Bu foydalanuvchini bloklamoqchimisiz?")) {
        socket.send(JSON.stringify({ type: 'block', user_id: userIdToBlock }));
    }
}

function scrollToMessage(id) {
    const target = document.querySelector(`.msg[data-id='${id}']`);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('highlight');
        setTimeout(() => {
            target.classList.remove('highlight');
        }, 1500);
    } else {
        alert("Asl xabar topilmadi.");
    }
}

// Scroll functions
function toggleScrollButton() {
    const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 50;
    scrollBtn.style.display = isAtBottom ? 'none' : 'flex';
}

function scrollToBottom() {
    chatBox.scrollTo({
        top: chatBox.scrollHeight,
        behavior: 'smooth'
    });
}

function positionScrollButton() {
    const form = document.getElementById('chat-form');
    const btn = document.getElementById('scrollToBottomBtn');
    if (!form || !btn) return;

    const rect = form.getBoundingClientRect();
    btn.style.position = 'fixed';
    btn.style.bottom = (window.innerHeight - rect.top + 40) + 'px';
    btn.style.right = '40px';
}

// Modal functions
function closeProfile() {
    document.getElementById("profile-popup").style.display = "none";
    document.getElementById("modal-overlay").style.display = "none";
}

function showUserProfilePopup(msg) {
    const popup = document.getElementById('user-profile-popup');
    const overlay = document.getElementById('user-profile-overlay');
    const content = document.getElementById('user-profile-content');

    content.innerHTML = `
        <div style="text-align: center;">
          <img src="${msg.profile_image}" alt="Avatar" width="200" height="200" style="border-radius: 50%; object-fit: cover; border: 2px solid #ccc;">
          <h5 class="mt-2" style="color: #7c7c7cff;">Username: <span style="color: #000;">${msg.username}</span></h5>
          <p style="margin: 0; color: #7c7c7cff;">Status: <span style="color: #000;">${msg.role || 'user'}</span></p>
          <p style="margin: 0; color: #7c7c7cff;">Last active: <span style="color: #000;">${msg.last_active || 'Noma’lum'}</span></p>
        </div>
    `;

    popup.style.display = 'block';
    overlay.style.display = 'block';
}

function closeUserProfilePopup() {
    document.getElementById('user-profile-popup').style.display = 'none';
    document.getElementById('user-profile-overlay').style.display = 'none';
}

// Event listeners
form.addEventListener('submit', e => {
    e.preventDefault();
    let text = input.value.trim();
    const replyTo = replyToInput.value;
    if (!text) return;
    if (text.length > 4096) text = text.slice(0, 4096);
    socket.send(JSON.stringify({
        type: 'send',
        message: text,
        reply_to: replyTo,
        user_id: userId
    }));
    input.value = '';
    input.dispatchEvent(new Event('input'));
    replyToInput.value = 0;
    replyPreview.style.display = 'none';
});

// Textarea auto-resize
const textarea = document.getElementById("message");
textarea.addEventListener("input", function () {
    this.style.height = "auto";
    this.style.height = (this.scrollHeight) + "px";
});

// Scroll button positioning
window.addEventListener('resize', positionScrollButton);
window.addEventListener('scroll', positionScrollButton);
window.addEventListener('load', positionScrollButton);
positionScrollButton();

// Chat box scroll event
chatBox.addEventListener('scroll', toggleScrollButton);
toggleScrollButton();

// Profile modal events
document.getElementById("profile-name").addEventListener("click", function () {
    document.getElementById("profile-popup").style.display = "block";
    document.getElementById("modal-overlay").style.display = "block";
});

document.getElementById("modal-overlay").addEventListener("click", closeProfile);

// Main js

function closeUserProfilePopup() {
    document.getElementById('user-profile-popup').style.display = 'none';
    document.getElementById('user-profile-overlay').style.display = 'none';
}

form.addEventListener('submit', e => {
    e.preventDefault();
    let text = input.value.trim();
    const replyTo = replyToInput.value;
    if (!text) return;
    if (text.length > 4096) text = text.slice(0, 4096);
    socket.send(JSON.stringify({
        type: 'send',
        message: text,
        reply_to: replyTo,
        user_id: userId
    }));
    input.value = '';
    replyToInput.value = 0;
    replyPreview.style.display = 'none';
});


function cancelReply() {
    replyToInput.value = 0;
    replyPreview.style.display = 'none';
}

function deleteMsg(id) {
    if (confirm("Xabarni o‘chirishni istaysizmi?")) {
        socket.send(JSON.stringify({
            type: 'delete',
            id: id
        }));
    }
}

function blockUser(userIdToBlock) {
    if (confirm("Bu foydalanuvchini bloklamoqchimisiz?")) {
        socket.send(JSON.stringify({
            type: 'block',
            user_id: userIdToBlock
        }));
    }
}

function scrollToMessage(id) {
    const target = document.querySelector(`.msg[data-id='${id}']`);
    if (target) {
        target.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
        target.classList.add('highlight');
        setTimeout(() => {
            target.classList.remove('highlight');
        }, 1500);
    } else {
        alert("Asl xabar topilmadi.");
    }
}

// Tugma bosilganda
function scrollToBottom() {
    chatBox.scrollTo({
        top: chatBox.scrollHeight,
        behavior: 'smooth'
    });
}

// Modalni ochish
document.getElementById("profile-name").addEventListener("click", function() {
    document.getElementById("profile-popup").style.display = "block";
    document.getElementById("modal-overlay").style.display = "block";
});
// Modalni yopish
function closeProfile() {
    document.getElementById("profile-popup").style.display = "none";
    document.getElementById("modal-overlay").style.display = "none";
}

// Modal tashqarisini bosganda yopish
document.getElementById("modal-overlay").addEventListener("click", closeProfile);

// Popup tashqarisiga bosganda yopish
window.addEventListener('click', function(e) {
    const popup = document.getElementById('user-popup');
    if (e.target === popup) {
        popup.style.display = 'none';
    }
});

//online-count and list

function openOnlineUsersModal() {
  document.getElementById('online-users-modal').style.display = 'block';
  document.getElementById('online-users-overlay').style.display = 'block';
  socket.send(JSON.stringify({ type: 'get_online_users' }));
}

function closeOnlineUsersModal() {
  document.getElementById('online-users-modal').style.display = 'none';
  document.getElementById('online-users-overlay').style.display = 'none';
}

function renderOnlineUsers(users) {
  const container = document.getElementById('online-users-list');
  container.innerHTML = '';

  users.forEach(user => {
    const profileImage = user.profile_image && user.profile_image.trim() !== ''
      ? user.profile_image
      : '/profile_image/default.jpg'; // fallback agar profile_image bo‘sh bo‘lsa

    const item = document.createElement('div');
    item.className = 'user-item';
    item.innerHTML = `
      <img src="${profileImage}" alt="${user.username}">
      <div><strong>${user.username}</strong></div>
    `;
    container.appendChild(item);
  });
}


// Initialize WebSocket connection
createSocket();
