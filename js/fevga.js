// fevga.js - FINAL STABLE VERSION
let selectedPieceId = null, 
currentDice = { d1: null, d2: null }, 
isMyTurn = false, 
boardState = [], 
isAnimating = false, 
currentMovesLeft = 0, 
timerInterval = null, 
timeLeft = 120, 
lastTurn = null, 
whiteReadyAlerted = false, 
blackReadyAlerted = false;

async function updateAll() { 
    if (isAnimating) return; 
    await checkGameStatus(); 
    await syncPlayers(); 
    await refreshBoard(); 
}

async function checkGameStatus() {
    try {
        const response = await fetch('tavli.php/status/');
        const status = await response.json();
        currentMovesLeft = parseInt(status.moves_left);  

        if (lastTurn !== status.p_turn) { resetTimer(); lastTurn = status.p_turn; }

        // ΕΛΕΓΧΟΣ ΝΙΚΗΣ
        if (status.status === 'ended') {
            isAnimating = true; // Σταματάμε τα αυτόματα refresh
            
            let winnerName = (status.result === 'W') ? pWhite : pBlack;
            let playAgain = confirm("Το παιχνίδι τελείωσε! Νικητής είναι ο " + winnerName + ".\n\nΘέλετε να παίξετε ξανά (επανεκκίνηση με διατήρηση σκορ);");
            
            if (playAgain) {
                // ΝΑΙ: Καλούμε την έναρξη (clean_board)
                await fetch('tavli.php/status/', { 
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'start' }) 
                });
                isAnimating = false;
                lastTurn = null; // Reset για το χρονόμετρο
                updateAll();
            } else {
                // ΟΧΙ: Έξοδος και μηδενισμός
                window.location.href = 'logout.php';
            }
            return;
        }

        // Σήμα Επανεκκίνησης (Online)
        if (status.result && status.result.startsWith('RESTART_')) {
            let win = status.result.split('_')[1], my = (myColor === 'white') ? 'W' : 'B';
            resetTimer(); if (win === my) alert("Ο αντίπαλος επέλεξε επανεκκίνηση παιχνιδιού. Κερδίσατε!");
            await fetch('tavli.php/status/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'clear_result' }) });
            return;
        }

        // Σήμα Αποχώρησης (Online)
        if (status.status === 'aborted') {
            alert(status.result === (myColor === 'white' ? 'W' : 'B') ? "Ο αντίπαλος αποχώρησε. Κερδίσατε!" : "Αποχωρήσατε.");
            window.location.href = 'index.php'; return;
        }

        currentDice.d1 = status.dice1; currentDice.d2 = status.dice2;
        if (typeof isHotseat !== 'undefined' && isHotseat === true) {
            myColor = (status.p_turn === 'W') ? 'white' : 'black'; isMyTurn = true; 
        } else {
            isMyTurn = (status.p_turn === 'W' && myColor === 'white') || (status.p_turn === 'B' && myColor === 'black');
        }

        // UI ΔΙΑΧΕΙΡΙΣΗ
        const btnStart = document.getElementById('btn-start-game');
        const gameControls = document.getElementById('game-controls');
        
        if (status.status === 'not active') {
            if(btnStart) btnStart.style.display = 'inline-block';
            if(gameControls) gameControls.style.display = 'none';
        } else {
            if(btnStart) btnStart.style.display = 'none'; // HIDE ΤΗΝ ΕΝΑΡΞΗ
            if(gameControls) gameControls.style.display = 'block';
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
        }
        document.getElementById('score-w').innerText = status.score_w || 0;
        document.getElementById('score-b').innerText = status.score_b || 0;
    } catch (e) { console.error(e); }
}


async function refreshBoard() {
    if (isAnimating) return;
    try {
        const response = await fetch('tavli.php/board/'), data = await response.json();
        boardState = data; 
        const statusRes = await fetch('tavli.php/status/'), statusData = await statusRes.json();

        // 1. Καθαρισμός
        document.querySelectorAll('.point, .off-zone').forEach(p => { 
            p.innerHTML = ''; p.classList.remove('possible-move'); p.onclick = null; 
        });

        // 2. Υπολογισμός αν μπορεί να μαζέψει (canCollect) - ΔΙΟΡΘΩΜΕΝΟ
        const myCode = (myColor === 'white' ? 'W' : 'B');
        
        // Μετράμε πόσα πούλια υπάρχουν ΕΚΤΟΣ της περιοχής μαζέματος
        const piecesOutside = boardState.filter(p => 
            p.piece_color === myCode && 
            parseInt(p.piece_count) > 0 && // <--- ΠΡΕΠΕΙ ΝΑ ΕΧΕΙ ΠΟΥΛΙΑ
            (myColor === 'white' ? parseInt(p.x) > 6 : (parseInt(p.x) < 13 || parseInt(p.x) > 18))
        ).length;

        const canCollect = (piecesOutside === 0);

        // Alert Ετοιμότητας (μόνο μία φορά)
        if (canCollect) {
            if (myColor === 'white' && !whiteReadyAlerted) {
                alert("Ο παίκτης (Άσπρα) είναι έτοιμος να μαζέψει τα πούλια!");
                whiteReadyAlerted = true;
            } else if (myColor === 'black' && !blackReadyAlerted) {
                alert("Ο παίκτης (Μαύρα) είναι έτοιμος να μαζέψει τα πούλια!");
                blackReadyAlerted = true;
            }
        }

        // 3. Σχεδίαση Πουλιών στο Ταμπλό
        data.forEach(pos => {
            const tri = document.getElementById('p' + pos.x);
            if(tri && parseInt(pos.piece_count) > 0) {
                for(let i=0; i<parseInt(pos.piece_count); i++) {
                    const pc = document.createElement('div');
                    pc.className = 'piece ' + (pos.piece_color === 'W' ? 'white-piece' : 'black-piece');
                    if (selectedPieceId === parseInt(pos.x) && i === parseInt(pos.piece_count) - 1) pc.classList.add('selected-piece');
                    if ((pos.piece_color === 'W' && myColor === 'white') || (pos.piece_color === 'B' && myColor === 'black')) {
                        pc.style.cursor = isMyTurn ? 'pointer' : 'default';
                        pc.onclick = (e) => { e.stopPropagation(); selectPiece(pos.x); };
                    }
                    tri.appendChild(pc);
                }
            }
        });

        // 4. Σχεδίαση στις Θήκες Εξόδου
        const exW = document.getElementById('exit-w'), exB = document.getElementById('exit-b');
        for(let i=0; i < statusData.w_off; i++) { let p = document.createElement('div'); p.className = 'piece white-piece'; exW.appendChild(p); }
        for(let i=0; i < statusData.b_off; i++) { let p = document.createElement('div'); p.className = 'piece black-piece'; exB.appendChild(p); }

        if (selectedPieceId !== null && isMyTurn) {
            showSuggestions(selectedPieceId, canCollect);
        }
    } catch (e) { console.error(e); }
}

function showSuggestions(startPos, canCollect) {
    const d1 = parseInt(currentDice.d1) || 0;
    const d2 = parseInt(currentDice.d2) || 0;
    const targets = new Set();
    const myCode = (myColor === 'white' ? 'W' : 'B');
    const startPosInt = parseInt(startPos);

    const getTarget = (s, val) => {
        let t = s - val;
        if (myColor === 'white') return t;
        else { if (s <= 12 && t < 1) t += 24; return t; }
    };

    const isBlocked = (t) => {
        const s = boardState.find(x => parseInt(x.x) === t);
        return (s && parseInt(s.piece_count) > 0 && s.piece_color !== myCode);
    };

    // Λογική κινήσεων
    const sq = boardState.find(s => parseInt(s.x) === startPosInt);
    const isMana = (sq && parseInt(sq.piece_count) === 15);

    if (isMana && currentMovesLeft === 1) {
        targets.add(getTarget(startPosInt, (d1 === 6 && d2 === 6) ? 6 : (d1 + d2)));
    } else if (d1 > 0 && d1 === d2) {
        for (let i = 1; i <= currentMovesLeft; i++) {
            let t = getTarget(startPosInt, i * d1);
            if (isBlocked(t)) break;
            targets.add(t);
        }
    } else {
        if (d1 > 0) {
            let t1 = getTarget(startPosInt, d1);
            if (!isBlocked(t1)) {
                targets.add(t1);
                if (d2 > 0) {
                    let ts = getTarget(startPosInt, d1 + d2);
                    if (!isBlocked(ts)) targets.add(ts);
                }
            }
        }
        if (d2 > 0) {
            let t2 = getTarget(startPosInt, d2);
            if (!isBlocked(t2)) {
                targets.add(t2);
                if (d1 > 0) {
                    let ts = getTarget(startPosInt, d1 + d2);
                    if (!isBlocked(ts)) targets.add(ts);
                }
            }
        }
    }

    // --- ΛΟΓΙΚΗ ΜΑΖΕΜΑΤΟΣ (ΠΡΑΣΙΝΙΣΜΑ) ---
    if (canCollect) {
        const dist = (myColor === 'white') ? startPosInt : (startPosInt - 12);
        const dice = [d1, d2].filter(v => v > 0);
        
        let canExit = false;
        dice.forEach(die => {
            if (die >= dist) canExit = true; // ΚΑΝΟΝΑΣ >= ΧΩΡΙΣ ΠΕΡΙΟΡΙΣΜΟΥΣ
        });

        if (canExit) {
            const zone = document.getElementById(myColor === 'white' ? 'exit-w' : 'exit-b');
            if (zone) {
                zone.classList.add('possible-move');
                zone.onclick = () => handleCollectClick(startPosInt);
            }
        }
    }

    // Πρασίνισμα στο ταμπλό
    targets.forEach(t => {
        if (t >= 1 && t <= 24 && !isBlocked(t)) {
            // Περιορισμός κύκλου
            if (myColor === 'white' && t >= startPosInt) return;
            if (myColor === 'black' && startPosInt >= 13 && t < 13) return; //startPosInt >= 13 &&

            const el = document.getElementById('p' + t);
            if (el) {
                el.classList.add('possible-move');
                el.onclick = () => handlePointClick(t);
            }
        }
    });
}

async function handleCollectClick(fromPos) {
    try {
        await fetch('tavli.php/status/', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'collect', from: fromPos, color: myColor })
        });
        selectedPieceId = null;
        await updateAll();
    } catch (e) { console.error(e); }
}

// Πρόσθεσε αυτό στην αρχή της refreshBoard() για να καθαρίζει το scoreboard
const sb = document.getElementById('scoreboard');
if(sb) { sb.classList.remove('possible-move'); sb.onclick = null; }

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