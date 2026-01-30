// fevga.js
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
let gameFinishedAlertShown = false;

async function updateAll() { 
    if (isAnimating) return; 
    await checkGameStatus(); 
    await syncPlayers(); 
    await refreshBoard(); 
}

async function checkGameStatus() {
    if (isAnimating) return;
    try {
        const response = await fetch('tavli.php/status/');
        const status = await response.json();
        currentMovesLeft = parseInt(status.moves_left); 

        if (lastTurn !== status.p_turn) { resetTimer(); lastTurn = status.p_turn; selectedPieceId = null; }

        if (status.status === 'ended') {
            // Αν ο αντίπαλος πάτησε ήδη "Ναι"
            if (status.result && status.result.includes('_READY')) {
                const overlay = document.getElementById('waiting-overlay');
                overlay.style.display = 'flex';
                overlay.querySelector('h1').innerText = "⏳ Αναμονή...";
                overlay.querySelector('p').innerText = "Ο αντίπαλος δέχτηκε την επανεκκίνηση! Περιμένουμε εσάς.";
                
                if (!gameFinishedAlertShown) {
                    gameFinishedAlertShown = true;
                    setTimeout(() => { askPlayAgain(status.result); }, 100);
                }
                return;
            }

            if (!gameFinishedAlertShown) {
                gameFinishedAlertShown = true;
                setTimeout(() => { askPlayAgain(status.result); }, 100);
            }
            return;
        }

        if (status.result && status.result.startsWith('RESTART_')) {
            let win = status.result.split('_')[1], my = (myColor === 'white') ? 'W' : 'B';
            resetTimer(); selectedPieceId = null;
            if (win === my) alert("Ο αντίπαλος επέλεξε επανεκκίνηση παιχνιδιού. Κερδίσατε τον πόντο!");
            await fetch('tavli.php/status/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'clear_result' }) });
            return;
        }

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
        
        document.getElementById('btn-start-game').style.display = 'none';
        document.getElementById('game-controls').style.display = (status.status === 'started' || status.status === 'first_roll') ? 'block' : 'none';
        document.getElementById('btn-pass').disabled = !isMyTurn;
        document.getElementById('turn-label-w').style.display = (status.p_turn === 'W') ? 'inline-block' : 'none';
        document.getElementById('turn-label-b').style.display = (status.p_turn === 'B') ? 'inline-block' : 'none';
        
        const hasDice = (status.dice1 !== null || status.dice2 !== null);
        if (isMyTurn && (!hasDice || currentMovesLeft === 0)) { document.getElementById('btn-roll').style.display = 'inline-block'; document.getElementById('dice-display').style.display = 'none'; }
        else { document.getElementById('btn-roll').style.display = 'none'; if(hasDice) document.getElementById('dice-display').style.display = 'block'; }
        if (hasDice && currentMovesLeft > 0) { document.getElementById('d1').innerText = status.dice1 || "-"; document.getElementById('d2').innerText = status.dice2 || "-"; }
        document.getElementById('score-w').innerText = status.score_w || 0; document.getElementById('score-b').innerText = status.score_b || 0;
    } catch (e) { console.error(e); }
}


async function refreshBoard() {
    if (isAnimating) return;
    try {
        const response = await fetch('tavli.php/board/');
        const data = await response.json();
        boardState = data; 

        const statusRes = await fetch('tavli.php/status/');
        const statusData = await statusRes.json();

        //Έλεγχος ετοιμότητας
        const hasWhiteOutside = data.some(p => p.piece_color === 'W' && parseInt(p.piece_count) > 0 && parseInt(p.x) > 6);
        const hasBlackOutside = data.some(p => p.piece_color === 'B' && parseInt(p.piece_count) > 0 && (parseInt(p.x) < 13 || parseInt(p.x) > 18));
        const hasWhiteOnBoard = data.some(p => p.piece_color === 'W' && parseInt(p.piece_count) > 0);
        const hasBlackOnBoard = data.some(p => p.piece_color === 'B' && parseInt(p.piece_count) > 0);

        const isWhiteReady = !hasWhiteOutside && hasWhiteOnBoard;
        const isBlackReady = !hasBlackOutside && hasBlackOnBoard;

        //Εμφάνιση/Απόκρυψη θηκών
        const exW = document.getElementById('exit-w');
        const exB = document.getElementById('exit-b');
        if (exW) isWhiteReady ? exW.classList.add('show-exit') : exW.classList.remove('show-exit');
        if (exB) isBlackReady ? exB.classList.add('show-exit') : exB.classList.remove('show-exit');

        if (isWhiteReady && !whiteReadyAlerted) { alert("Ο παίκτης (Άσπρα) είναι έτοιμος να μαζέψει τα πούλια!"); whiteReadyAlerted = true; }
        else if (hasWhiteOutside) { whiteReadyAlerted = false; }
        if (isBlackReady && !blackReadyAlerted) { alert("Ο παίκτης (Μαύρα) είναι έτοιμος να μαζέψει τα πούλια!"); blackReadyAlerted = true; }
        else if (hasBlackOutside) { blackReadyAlerted = false; }

        //Καθαρισμός ταμπλό και θηκών
        document.querySelectorAll('.point, .off-zone').forEach(p => { 
            p.innerHTML = ''; 
            p.classList.remove('possible-move'); 
            p.onclick = null; 
        });

        data.forEach(pos => {
            const tri = document.getElementById('p' + pos.x);
            if(tri && parseInt(pos.piece_count) > 0) {
                for(let i=0; i<parseInt(pos.piece_count); i++) {
                    const pc = document.createElement('div');
                    pc.className = 'piece ' + (pos.piece_color === 'W' ? 'white-piece' : 'black-piece');
                    if (selectedPieceId === parseInt(pos.x) && i === parseInt(pos.piece_count) - 1) pc.classList.add('selected-piece');
                    
                    const isMine = (pos.piece_color === 'W' && myColor === 'white') || (pos.piece_color === 'B' && myColor === 'black');
                    if (isMine && isMyTurn) {
                        pc.style.cursor = 'pointer';
                        pc.onclick = (e) => {
                            e.stopPropagation();
                            selectPiece(pos.x);
                        };
                    }
                    tri.appendChild(pc);
                }
            }
        });

        //Σχεδίαση Πουλιών στις Θήκες Εξόδου
        if(exW) for(let i=0; i < statusData.w_off; i++) { let p = document.createElement('div'); p.className = 'piece white-piece'; exW.appendChild(p); }
        if(exB) for(let i=0; i < statusData.b_off; i++) { let p = document.createElement('div'); p.className = 'piece black-piece'; exB.appendChild(p); }

        //Εμφάνιση προτάσεων
        if (selectedPieceId !== null && isMyTurn) {
            const ready = (myColor === 'white' ? isWhiteReady : isBlackReady);
            showSuggestions(selectedPieceId, ready);
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
        if (myColor === 'black' && s <= 12 && t < 1) t += 24;
        return t;
    };
    const isBlocked = (t) => {
        const s = boardState.find(x => parseInt(x.x) === t);
        return (s && parseInt(s.piece_count) > 0 && s.piece_color !== myCode);
    };

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
                if (d2 > 0) { let ts = getTarget(startPosInt, d1 + d2); if (!isBlocked(ts)) targets.add(ts); }
            }
        }
        if (d2 > 0) { 
            let t2 = getTarget(startPosInt, d2);
            if (!isBlocked(t2)) {
                targets.add(t2);
                if (d1 > 0) { let ts = getTarget(startPosInt, d1 + d2); if (!isBlocked(ts)) targets.add(ts); }
            }
        }
    }

    targets.forEach(t => {
        let isExit = (myColor === 'white' && t < 1) || (myColor === 'black' && startPosInt >= 13 && t < 13);
        
        if (isExit) {
            if (canCollect) {
                const dist = (myColor === 'white') ? startPosInt : (startPosInt - 12);
                if (d1 >= dist || d2 >= dist) {
                    const zone = document.getElementById(myColor === 'white' ? 'exit-w' : 'exit-b');
                    if (zone) { zone.classList.add('possible-move'); zone.onclick = () => handleCollectClick(startPosInt); }
                }
            }
        } else if (t >= 1 && t <= 24 && !isBlocked(t)) {
            if (myCode === 'B') {
                if (startPosInt <= 12) {
                    // Αν είμαστε στο 1-12, δεν μπορούμε να πάμε σε μεγαλύτερη θέση στο 1-12
                    if (t <= 12 && t >= startPosInt) return;
                } else {
                    // Αν είμαστε στο 13-24, απαγορεύεται να πάμε στο 1-12
                    if (t < 13) return;
                }
            }
            
            // Φράγμα για τα Άσπρα (να μην πάνε πίσω)
            if (myCode === 'W' && t >= startPosInt) return;

            const el = document.getElementById('p' + t);
            if (el) { el.classList.add('possible-move'); el.onclick = () => handlePointClick(t); }
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

async function syncPlayers() {
    try {
        const resS = await fetch('tavli.php/status/');
        const statusData = await resS.json();

        // ΑΝ ΤΟ ΠΑΙΧΝΙΔΙ ΤΕΛΕΙΩΣΕ, ΠΑΓΩΝΟΥΜΕ ΤΑ ΠΑΝΤΑ
        if (statusData.status === 'ended' || (statusData.result && statusData.result.includes('_READY'))) {
            return; 
        }

        const resW = await fetch('tavli.php/player/W');
        const dataW = await resW.json();
        const resB = await fetch('tavli.php/player/B');
        const dataB = await resB.json();

        const nameWExist = (dataW.length > 0 && dataW[0].username);
        const nameBExist = (dataB.length > 0 && dataB[0].username);

        if (!isHotseat) {
            // Μόνο αν το παιχνίδι είναι 'not active' και η βάση άδεια φεύγουμε
            if (!nameWExist && !nameBExist && statusData.status === 'not active') {
                window.location.href = 'index.php';
                return;
            }
        }

        //Ενημέρωση των labels κάτω από το ταμπλό
        document.getElementById('p-name-w').innerText = nameWExist ? dataW[0].username : "Αναμονή...";
        document.getElementById('p-name-b').innerText = nameBExist ? dataB[0].username : "Αναμονή...";

        //Ενημέρωση του Scoreboard
        document.getElementById('score-name-w').innerText = nameWExist ? dataW[0].username : "Αναμονή...";
        document.getElementById('score-name-b').innerText = nameBExist ? dataB[0].username : "Αναμονή...";

        const overlay = document.getElementById('waiting-overlay');
        if (!isHotseat) {
            overlay.style.display = (nameWExist && nameBExist) ? 'none' : 'flex';
        }
    } catch (e) { console.error("Sync Error:", e); }
}

async function askPlayAgain(resultCode) {
    let winnerCode = resultCode.substring(0,1), name = (winnerCode === 'W') ? pWhite : pBlack;
    if (confirm("Νικητής: " + name + "\nΘέλετε να παίξετε ξανά;")) {
        isAnimating = false; // Ξεκλειδώνουμε το refresh
        if (isHotseat) {
            await fetch('tavli.php/status/', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'start' }) });
            gameFinishedAlertShown = false; updateAll();
        } else {
            await fetch('tavli.php/status/', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'play_again' }) });
            updateAll();
        }
    } else { window.location.href = 'logout.php'; }
}

// // Πρόσθεσε αυτό στην αρχή της refreshBoard() για να καθαρίζει το scoreboard
// const sb = document.getElementById('scoreboard');
// if(sb) { sb.classList.remove('possible-move'); sb.onclick = null; }

function resetTimer() { 
    clearInterval(timerInterval); 
    timeLeft = 120; 
    timerInterval = setInterval(() => { 
        timeLeft--; 
        updateTimerDisplay(); 
        if (timeLeft <= 0) { 
            clearInterval(timerInterval); 
            if (isMyTurn) passTurn(); 
            } 
        }, 1000); 
}

function updateTimerDisplay() { 
    const m = Math.floor(timeLeft / 60), s = timeLeft % 60; 
    const el = document.getElementById('timer-display'); 
    if (el) el.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`; 
}

async function passTurn() { 
    await fetch('tavli.php/status/', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/json'}, 
        body: JSON.stringify({ action: 'pass' }) 
    }); 
    updateAll(); 
}

async function startGame() { 
    await fetch('tavli.php/status/', { 
        method: 'POST', 
        body: JSON.stringify({ action: 'start' }) 
    }); 
    updateAll(); 
}

async function rollDice() { 
    await fetch('tavli.php/status/', { 
        method: 'POST' 
    }); 
    await updateAll(); 
}

function selectPiece(pos) { 
    selectedPieceId = (selectedPieceId === parseInt(pos)) ? null : parseInt(pos); 
    updateAll(); 
}

async function handlePointClick(t) { 
    if (selectedPieceId === null) return; 
    try { 
        const res = await fetch('tavli.php/status/', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify({ action: 'move', from: selectedPieceId, to: parseInt(t), color: myColor }) 
        }); 
        const d = await res.json(); 
        if(d.error) alert(d.error); 
        selectedPieceId = null; 
        await updateAll(); 
    } 
    catch (e) { 
        console.error(e); 
    } 
}

async function resetGame() { 
    if (typeof isHotseat !== 'undefined' && isHotseat === true) { 
        if(confirm("Επανεκκίνηση;")) { 
            await fetch('tavli.php/board/', { 
                method: 'POST' 
            }); 
            resetTimer(); 
            updateAll(); 
        } 
    } 
    else { 
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

document.addEventListener('DOMContentLoaded', () => { 
    updateAll(); 
    if (!isHotseat) setInterval(updateAll, 3000); 
});