// fevga.js - FINAL STABLE VERSION
let selectedPieceId = null, currentDice = { d1: null, d2: null }, isMyTurn = false, boardState = [], isAnimating = false, currentMovesLeft = 0, timerInterval = null, timeLeft = 120, lastTurn = null, whiteReadyAlerted = false, blackReadyAlerted = false;

async function updateAll() { if (isAnimating) return; await checkGameStatus(); await syncPlayers(); await refreshBoard(); }

async function checkGameStatus() {
    try {
        const response = await fetch('tavli.php/status/'), status = await response.json();
        currentMovesLeft = parseInt(status.moves_left);
        if (lastTurn !== status.p_turn) { resetTimer(); lastTurn = status.p_turn; }
        
        // Σήμα Επανεκκίνησης
        if (status.result && status.result.startsWith('RESTART_')) {
            let win = status.result.split('_')[1], my = (myColor === 'white') ? 'W' : 'B';
            resetTimer(); if (win === my) alert("Ο αντίπαλος επέλεξε επανεκκίνηση παιχνιδιού. Κερδίσατε τον πόντο!");
            await fetch('tavli.php/status/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'clear_result' }) });
            return;
        }

        // Σήμα Αποχώρησης
        if (status.status === 'aborted') {
            let myCode = (myColor === 'white') ? 'W' : 'B';
            alert(status.result === myCode ? "Ο αντίπαλος αποχώρησε. Κερδίσατε!" : "Αποχωρήσατε από το παιχνίδι.");
            window.location.href = 'index.php';
            return;
        }

        currentDice.d1 = status.dice1; currentDice.d2 = status.dice2;
        
        if (typeof isHotseat !== 'undefined' && isHotseat === true) {
            myColor = (status.p_turn === 'W') ? 'white' : 'black';
            isMyTurn = true; 
        } else {
            isMyTurn = (status.p_turn === 'W' && myColor === 'white') || (status.p_turn === 'B' && myColor === 'black');
        }

        // UI Διαχείριση
        document.getElementById('btn-start-game').style.display = 'none';
        document.getElementById('game-controls').style.display = (status.status === 'started' || status.status === 'first_roll') ? 'block' : 'none';
        document.getElementById('btn-pass').disabled = !isMyTurn;
        document.getElementById('turn-label-w').style.display = (status.p_turn === 'W') ? 'inline-block' : 'none';
        document.getElementById('turn-label-b').style.display = (status.p_turn === 'B') ? 'inline-block' : 'none';
        
        const hasDice = (status.dice1 !== null || status.dice2 !== null);
        if (isMyTurn && (!hasDice || currentMovesLeft === 0)) {
            document.getElementById('btn-roll').style.display = 'inline-block';
            document.getElementById('dice-display').style.display = 'none'; 
        } else {
            document.getElementById('btn-roll').style.display = 'none';
            if(hasDice) document.getElementById('dice-display').style.display = 'block';
        }

        if (hasDice && currentMovesLeft > 0) {
            document.getElementById('d1').innerText = status.dice1 || "-";
            document.getElementById('d2').innerText = status.dice2 || "-";
        }
        document.getElementById('score-w').innerText = status.score_w || 0;
        document.getElementById('score-b').innerText = status.score_b || 0;
    } catch (e) { console.error(e); }
}

async function refreshBoard() {
    try {
        const res = await fetch('tavli.php/board/'), data = await res.json(); boardState = data;
        
        // Alert για μάζεμα
        let wH = 0, bH = 0; data.forEach(p => { if (p.piece_color === 'W' && p.x <= 6) wH += parseInt(p.piece_count); if (p.piece_color === 'B' && p.x >= 13 && p.x <= 18) bH += parseInt(p.piece_count); });
        if (wH === 15 && !whiteReadyAlerted) { alert("Ο παίκτης (Άσπρα) είναι έτοιμος να μαζέψει τα πούλια!"); whiteReadyAlerted = true; } else if (wH < 15) whiteReadyAlerted = false;
        if (bH === 15 && !blackReadyAlerted) { alert("Ο παίκτης (Μαύρα) είναι έτοιμος να μαζέψει τα πούλια!"); blackReadyAlerted = true; } else if (bH < 15) blackReadyAlerted = false;

        document.querySelectorAll('.point').forEach(p => { p.innerHTML = ''; p.classList.remove('possible-move'); });
        
        data.forEach(pos => {
            const tri = document.getElementById('p' + pos.x);
            if(tri && parseInt(pos.piece_count) > 0) {
                for(let i=0; i<parseInt(pos.piece_count); i++) {
                    const pc = document.createElement('div');
                    pc.className = 'piece ' + (pos.piece_color === 'W' ? 'white-piece' : 'black-piece');
                    if (selectedPieceId === parseInt(pos.x) && i === parseInt(pos.piece_count) - 1) pc.classList.add('selected-piece');
                    
                    // ΔΙΟΡΘΩΣΗ: Προσθήκη Cursor Pointer (το χεράκι)
                    const isMine = (pos.piece_color === 'W' && myColor === 'white') || (pos.piece_color === 'B' && myColor === 'black');
                    if (isMine) {
                        pc.style.cursor = isMyTurn ? 'pointer' : 'default';
                        pc.onclick = (e) => { e.stopPropagation(); if (isMyTurn) selectPiece(pos.x); };
                    }
                    tri.appendChild(pc);
                }
            }
        });
        if (selectedPieceId !== null && isMyTurn) showSuggestions(selectedPieceId);
    } catch (e) { console.error(e); }
}

function showSuggestions(startPos) {
    const d1 = parseInt(currentDice.d1) || 0, d2 = parseInt(currentDice.d2) || 0, targets = new Set();
    const sq = boardState.find(s => parseInt(s.x) === startPos), isMana = (sq && parseInt(sq.piece_count) === 15);
    
    // ΔΙΟΡΘΩΜΕΝΟ TARGET LOGIC ΓΙΑ LAP CONTROL
    const getTarget = (s, val) => {
        let t = s - val;
        if (myColor === 'white') return (t < 1 || t >= s) ? null : t;
        else { 
            if (s <= 12 && t < 1) t += 24; 
            return (s >= 13 && t < 13) ? null : t; 
        }
    };
    const isAvail = (t) => { if (!t) return false; const s = boardState.find(x => parseInt(x.x) === t); return !(s && s.piece_count > 0 && s.piece_color !== (myColor === 'white' ? 'W' : 'B')); };

    if (isMana && currentMovesLeft === 1) { 
        // ΠΡΩΤΗ ΚΙΝΗΣΗ: Μόνο το άθροισμα
        let t = getTarget(startPos, (d1 === 6 && d2 === 6) ? 6 : (d1 + d2));
        if (isAvail(t)) targets.add(t);
    } else if (d1 > 0 && d1 === d2) {
        for (let i = 1; i <= currentMovesLeft; i++) { let t = getTarget(startPos, i * d1); if (isAvail(t)) targets.add(t); else break; }
    } else {
        [d1, d2].filter(v => v > 0).forEach(v => {
            let t = getTarget(startPos, v);
            if (isAvail(t)) {
                targets.add(t);
                let o = (v === d1) ? d2 : d1;
                if (o > 0) {
                    let ts = getTarget(startPos, d1 + d2);
                    if (isAvail(ts)) targets.add(ts);
                }
            }
        });
    }
    targets.forEach(t => { if(t) { 
        const el = document.getElementById('p' + t);
        el.classList.add('possible-move');
        el.onclick = () => handlePointClick(t);
    }});
}

function resetTimer() { clearInterval(timerInterval); timeLeft = 120; timerInterval = setInterval(() => { timeLeft--; updateTimerDisplay(); if (timeLeft <= 0) { clearInterval(timerInterval); if (isMyTurn) passTurn(); } }, 1000); }
function updateTimerDisplay() { const m = Math.floor(timeLeft / 60), s = timeLeft % 60; const el = document.getElementById('timer-display'); if (el) el.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`; }
async function passTurn() { await fetch('tavli.php/status/', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'pass' }) }); updateAll(); }
async function startGame() { await fetch('tavli.php/status/', { method: 'POST', body: JSON.stringify({ action: 'start' }) }); updateAll(); }
async function rollDice() { await fetch('tavli.php/status/', { method: 'POST' }); await updateAll(); }
function selectPiece(pos) { selectedPieceId = (selectedPieceId === parseInt(pos)) ? null : parseInt(pos); updateAll(); }
async function handlePointClick(t) { if (selectedPieceId === null) return; try { const res = await fetch('tavli.php/status/', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'move', from: selectedPieceId, to: parseInt(t), color: myColor }) }); const d = await res.json(); if(d.error) alert(d.error); selectedPieceId = null; await updateAll(); } catch (e) { console.error(e); } }
async function resetGame() { if (typeof isHotseat !== 'undefined' && isHotseat === true) { if(confirm("Επανεκκίνηση;")) { await fetch('tavli.php/board/', { method: 'POST' }); resetTimer(); updateAll(); } } else { if(confirm("Είστε σίγουρος ότι θέλετε να παίξετε από την αρχή? Ο πόντος θα πάει στον αντίπαλο.")) { await fetch('tavli.php/status/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'reset_online', color: myColor }) }); resetTimer(); updateAll(); } } }
async function syncPlayers() { try { const rW = await fetch('tavli.php/player/W'), dW = await rW.json(), rB = await fetch('tavli.php/player/B'), dB = await rB.json(); if (dW.length > 0) document.getElementById('p-name-w').innerText = dW[0].username; if (dB.length > 0) document.getElementById('p-name-b').innerText = dB[0].username; } catch (e) { console.error(e); } }
document.addEventListener('DOMContentLoaded', () => { updateAll(); if (!isHotseat) setInterval(updateAll, 5000); });