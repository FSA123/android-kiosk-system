# 🚀 Kiosk Digital Media System

O soluție completă de tip "Offline-First" pentru gestionarea conținutului digital pe dispozitive Android, cu sincronizare automată de la un server central și dashboard de monitorizare.

## 🌟 Caracteristici
- **Sincronizare Automată:** Tableta descarcă automat fișierele noi de pe PC și curăță conținutul vechi.
- **Player Robust:** Rulează local (HTML5/JS), eliminând buffering-ul și dependența de internet constant.
- **Analytics în Timp Real:** Dashboard integrat cu grafice (Chart.js) pentru vizualizarea rulărilor media.
- **Arhitectură Eficientă:** Comunicare asincronă între PHP și JavaScript pentru a nu întrerupe experiența utilizatorului.

## 🛠️ Tehnologii
- **Backend:** PHP (Server-side logic & Sync)
- **Frontend:** HTML5, CSS3, JavaScript (Fetch API)
- **Server Local:** KSWEB (Android) / XAMPP (PC)
- **Vizualizare date:** Chart.js

## 📂 Structura Proiectului
- `/pc` - Conține scripturile de primire log-uri și Dashboard-ul.
- `/tablet` - Conține player-ul, scriptul de sincronizare și log-ul local.

## ⚙️ Instalare Rapidă
1. Clonează repository-ul.
2. Configurează IP-ul serverului PC în `sync.php`.
3. Setează permisiunile de scriere pe tabletă în aplicația KSWEB.
4. Rulează `index.html` pe tabletă folosind un Kiosk Browser.

---
Creat cu ❤️ pentru digital signage eficient.
