// fevga.js - FINAL VERSION 

let selectedPieceId = null;
let currentDice = { d1: null, d2: null };
let isMyTurn = false;
let boardState = []; 
let isAnimating = false; 
let currentMovesLeft = 0; 

// Μεταβλητές Ρολογιού
let timerInterval = null;
let timeLeft = 120; 
let lastTurn = null;


async function startGame() { 
    try {
        let res = await fetch('tavli.php/status/', { 
            method: 'POST', 
            body: JSON.stringify({ action: 'start' }) 
        }); 
        updateAll(); 
    } catch(e) { 
        console.error(e); 
    }
}

async function syncPlayers() {
    try {
        const resW = await fetch('tavli.php/player/W');
        const dataW = await resW.json();
        const resB = await fetch('tavli.php/player/B');
        const dataB = await resB.json();

        if (dataW.length > 0 && dataW[0].username) {
            document.getElementById('p-name-w').innerText = dataW[0].username;
            document.getElementById('score-name-w').innerText = dataW[0].username;
        }
        if (dataB.length > 0 && dataB[0].username) {
            document.getElementById('p-name-b').innerText = dataB[0].username;
            document.getElementById('score-name-b').innerText = dataB[0].username;
        }

        const overlay = document.getElementById('waiting-overlay');
        if (typeof isHotseat !== 'undefined' && isHotseat === false) {
            const player1Ready = (dataW.length > 0 && dataW[0].username);
            const player2Ready = (dataB.length > 0 && dataB[0].username);
            overlay.style.display = (player1Ready && player2Ready) ? 'none' : 'flex';
        } else {
            overlay.style.display = 'none';
        }
    } catch (e) { console.error(e); }
}

async function updateAll() {
    if (isAnimating) return;
    await checkGameStatus(); 
    await syncPlayers();
    await refreshBoard();
}


async function refreshBoard() {
    if (isAnimating) return;
    try {
        const response = await fetch('tavli.php/board/');
        const data = await response.json();
        boardState = data; 

        for(let i=1; i<=24; i++) {
            const point = document.getElementById('p'+i);
            if(point) {
                point.innerHTML = ''; 
                point.className = 'point'; 
                const newPoint = point.cloneNode(true);
                point.parentNode.replaceChild(newPoint, point);
                newPoint.onclick = () => { if(newPoint.classList.contains('possible-move')) handlePointClick(i); };
            }
        }

        data.forEach(pos => {
            const currentPos = parseInt(pos.x); 
            const count = parseInt(pos.piece_count);
            const triangle = document.getElementById('p' + currentPos);
            if(triangle && count > 0) {
                for(let i=0; i<count; i++) {
                    const piece = document.createElement('div');
                    const isWhite = pos.piece_color === 'W';
                    piece.className = 'piece ' + (isWhite ? 'white-piece' : 'black-piece');
                    if (selectedPieceId === currentPos && i === count - 1) { piece.classList.add('selected-piece'); }
                    const isMine = (isWhite && myColor === 'white') || (!isWhite && myColor === 'black');
                    if (isMine) {
                        piece.style.cursor = isMyTurn ? 'pointer' : 'not-allowed';
                        piece.onclick = (e) => { e.stopPropagation(); if (!isMyTurn) return; selectPiece(currentPos); };
                    }
                    triangle.appendChild(piece);
                }
            }
        });
        if (selectedPieceId !== null && isMyTurn) showSuggestions(selectedPieceId);
    } catch (error) { console.error(error); }
}


async function checkGameStatus() {
    if (isAnimating) return;
    try {
        const response = await fetch('tavli.php/status/');
        if (!response.ok) throw new Error("Status Error");
        const status = await response.json();
        
        currentMovesLeft = parseInt(status.moves_left); 

        // 1. ΔΙΑΧΕΙΡΙΣΗ ΡΟΛΟΓΙΟΥ (Αλλαγή Σειράς)
        if (lastTurn !== status.p_turn) {
            resetTimer(); 
            lastTurn = status.p_turn;
        }

        // 2. ΕΛΕΓΧΟΣ ΓΙΑ RESET MESSAGE (Online Mode)
        if (status.status === 'started' && status.result !== null) {
            let winnerCode = status.result; 
            let myCode = (myColor === 'white') ? 'W' : 'B';
            
            if (winnerCode === myCode) {
                alert("Ο αντίπαλος επέλεξε επανεκκίνηση παιχνιδιού. Κερδίσατε!");
            }
            
            resetTimer(); // ΜΗΔΕΝΙΣΜΟΣ ΡΟΛΟΓΙΟΥ λόγω επανεκκίνησης

            await fetch('tavli.php/status/', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_result' })
            });
        }

        // 3. ΕΛΕΓΧΟΣ ΑΚΥΡΩΣΗΣ / ΕΞΟΔΟΥ (Online Mode)
        if (status.status === 'aborted') {
            let myColorCode = (myColor === 'white') ? 'W' : 'B';
            if (status.result === myColorCode) {
                alert("Ο αντίπαλος αποχώρησε. Κερδίσατε τον πόντο!");
            } else {
                alert("Το παιχνίδι τερματίστηκε.");
            }
            window.location.href = 'index.php';
            return;
        }

        // 4. ΔΙΑΧΕΙΡΙΣΗ ΣΕΙΡΑΣ & ΧΡΩΜΑΤΟΣ
        currentDice.d1 = status.dice1;
        currentDice.d2 = status.dice2;
        if (typeof isHotseat !== 'undefined' && isHotseat === true) {
            myColor = (status.p_turn === 'W') ? 'white' : 'black';
            isMyTurn = true; 
        } else {
            isMyTurn = (status.p_turn === 'W' && myColor === 'white') || (status.p_turn === 'B' && myColor === 'black');
        }

        // 5. ΕΛΕΓΧΟΣ UI ΣΤΟΙΧΕΙΩΝ
        const btnStart = document.getElementById('btn-start-game');
        const btnRoll = document.getElementById('btn-roll');
        const btnPass = document.getElementById('btn-pass');
        const diceDisplay = document.getElementById('dice-display');
        const gameControls = document.getElementById('game-controls'); 

        if (status.status === 'started' || status.status === 'first_roll') {
            if(btnStart) btnStart.style.display = 'none';
            if(gameControls) gameControls.style.display = 'block';
            if (btnPass) btnPass.disabled = !isMyTurn;

            document.getElementById('turn-label-w').style.display = (status.p_turn === 'W') ? 'inline-block' : 'none';
            document.getElementById('turn-label-b').style.display = (status.p_turn === 'B') ? 'inline-block' : 'none';
            
            const hasDice = (status.dice1 !== null || status.dice2 !== null);
            if (isMyTurn && (!hasDice || currentMovesLeft === 0)) {
                if(btnRoll) btnRoll.style.display = 'inline-block';
                if(diceDisplay) diceDisplay.style.display = 'none'; 
            } else {
                if(btnRoll) btnRoll.style.display = 'none';
                if(hasDice) diceDisplay.style.display = 'block';
            }

            if (hasDice && currentMovesLeft > 0) {
                if(diceDisplay) diceDisplay.style.display = 'block';
                document.getElementById('d1').innerText = status.dice1 || "-";
                document.getElementById('d2').innerText = status.dice2 || "-";
            }
        }

        const scoreW = document.getElementById('score-w');
        const scoreB = document.getElementById('score-b');
        if(scoreW) scoreW.innerText = status.score_w || 0;
        if(scoreB) scoreB.innerText = status.score_b || 0;

    } catch (e) { console.error(e); }
}


function resetTimer() {
    clearInterval(timerInterval);
    timeLeft = 120;
    updateTimerDisplay();
    timerInterval = setInterval(() => {
        timeLeft--;
        updateTimerDisplay();
        if (timeLeft <= 0) { clearInterval(timerInterval); if (isMyTurn) passTurn(); }
    }, 1000);
}

function updateTimerDisplay() {
    const min = Math.floor(timeLeft / 60);
    const sec = timeLeft % 60;
    const el = document.getElementById('timer-display');
    if (el) {
        el.innerText = `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
        el.style.color = (timeLeft <= 10) ? "#e74c3c" : "#f1c40f";
    }
}

async function passTurn() {
    if (!isMyTurn) return;
    await fetch('tavli.php/status/', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/json'}, 
        body: JSON.stringify({ action: 'pass' }) 
    });
    updateAll();
}

async function rollDice() { try { await fetch('tavli.php/status/', { method: 'POST' }); checkGameStatus(); } catch(e) { console.error(e); } }

function selectPiece(position) { selectedPieceId = (selectedPieceId === parseInt(position)) ? null : parseInt(position); updateAll(); }

async function handlePointClick(targetPos) {
    if (selectedPieceId === null) return;
    try {
        const response = await fetch('tavli.php/status/', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'move', from: selectedPieceId, to: parseInt(targetPos), color: myColor })
        });
        const res = await response.json();
        if(res.error) alert(res.error);
        else { selectedPieceId = null; await checkGameStatus(); await refreshBoard(); }
    } catch (e) { console.error(e); }
}


function showSuggestions(startPos) {
    const d1 = parseInt(currentDice.d1) || 0;
    const d2 = parseInt(currentDice.d2) || 0;
    const targets = new Set();
    const selectedSquare = boardState.find(sq => parseInt(sq.x) === startPos);
    const isMana = (selectedSquare && parseInt(selectedSquare.piece_count) === 15);

    const getValidTarget = (start, steps) => {
        let t = start - steps;
        if (myColor === 'white') {
            return (t < 1 || t >= start) ? null : t; // Μόνο μπροστά
        } else {
            if (start <= 12 && t < 1) t += 24; 
            return (start >= 13 && t < 13) ? null : t; // Μόνο μπροστά
        }
    };

    const isAvailable = (t) => {
        if (!t) return false;
        const sq = boardState.find(s => parseInt(s.x) === t);
        return !(sq && sq.piece_count > 0 && sq.piece_color !== (myColor === 'white' ? 'W' : 'B'));
    };

    if (isMana && currentMovesLeft === 1) {
        let t = getValidTarget(startPos, (d1 === 6 && d2 === 6) ? 6 : (d1 + d2));
        if (isAvailable(t)) targets.add(t);
    } 
    else if (d1 > 0 && d1 === d2) {
        for (let i = 1; i <= currentMovesLeft; i++) {
            let t = getValidTarget(startPos, i * d1);
            if (isAvailable(t)) targets.add(t); else break;
        }
    } else {
        [d1, d2].filter(d => d > 0).forEach(val => {
            let t = getValidTarget(startPos, val);
            if (isAvailable(t)) {
                targets.add(t);
                let other = (val === d1) ? d2 : d1;
                if (other > 0) {
                    let tSum = getValidTarget(startPos, d1 + d2);
                    if (isAvailable(tSum)) targets.add(tSum);
                }
            }
        });
    }
    targets.forEach(t => { if(t) document.getElementById('p' + t).classList.add('possible-move'); });
}


async function resetGame() {
    if (typeof isHotseat !== 'undefined' && isHotseat === true) {
        if(confirm("Επανεκκίνηση;")) { 
            await fetch('tavli.php/board/', { method: 'POST' }); 
            resetTimer();
            updateAll(); 
        }
    } else {
        if(confirm("Είστε σίγουρος ότι θέλετε να παίξετε από την αρχή? Ο πόντος θα πάει στον αντίπαλο.")) {
            await fetch('tavli.php/status/', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset_online', color: myColor })
            });
            resetTimer();
            updateAll();
        }
    }
}

async function surrender(color) { 
    if(confirm("Give up?")) { 
        await fetch('tavli.php/status/', { 
            method: 'POST', 
            body: JSON.stringify({ action: 'surrender', color: color }) 
        }); 
        updateAll(); 
    } 
}

async function resetGameSilent() {
    await fetch('tavli.php/board/', { method: 'POST' });
    updateAll();
}


document.addEventListener('DOMContentLoaded', async () => { 
    updateAll(); 
    if (isHotseat === false) { setInterval(updateAll, 5000); }
});