// fevga.js - FINAL VERSION (With 3s Delay on First Roll)

let selectedPieceId = null;
let currentDice = { d1: null, d2: null };
let isMyTurn = false;
let boardState = []; 
// Μεταβλητή για να "παγώνει" την ανανέωση όσο βλέπουμε τα ζάρια
let isAnimating = false; 

async function updateAll() {
    // Αν βλέπουμε animation ζαριάς, μην κάνεις update
    if (isAnimating) return; 
    await checkGameStatus();
    await refreshBoard();
}

// --- ROLL FIRST (ΕΔΩ ΕΓΙΝΕ Η ΑΛΛΑΓΗ) ---
async function rollFirst() {
    try {
        const response = await fetch('tavli.php/status/', { 
            method: 'POST', 
            body: JSON.stringify({ action: 'roll_first' }) 
        }); 
        const res = await response.json(); // Παίρνουμε τα δεδομένα

        // 1. Ενημερώνουμε ΧΕΙΡΟΚΙΝΗΤΑ τα ζάρια για να τα δει ο χρήστης αμέσως
        const diceDisplay = document.getElementById('dice-display');
        const d1 = document.getElementById('d1');
        const d2 = document.getElementById('d2');
        
        if(diceDisplay) diceDisplay.style.display = 'block';
        if(d1 && res.dice1) d1.innerText = res.dice1;
        if(d2 && res.dice2) d2.innerText = res.dice2;

        // 2. ΕΛΕΓΧΟΣ: Ρίξαμε το δεύτερο ζάρι;
        if (res.dice2 !== null) {
            // ΝΑΙ: Κλείδωσε την ανανέωση για 3 δευτερόλεπτα
            isAnimating = true;

            setTimeout(() => {
                // Μετά από 3 δευτερόλεπτα, ξεκλείδωσε και κάνε update
                isAnimating = false;
                checkGameStatus();
            }, 3000); // 3000ms = 3 δευτερόλεπτα
        } else {
            // ΟΧΙ: Είναι μόνο το πρώτο ζάρι, προχώρα κανονικά
            checkGameStatus(); 
        }

    } catch(e) { 
        console.error(e); 
        isAnimating = false; // Σε περίπτωση λάθους, ξεκλείδωσε
    }
}

async function startGame() { 
    try {
        await fetch('tavli.php/status/', { method: 'POST', body: JSON.stringify({ action: 'start' }) }); 
        updateAll(); 
    } catch(e) { console.error(e); }
}

async function rollDice() { 
    try {
        await fetch('tavli.php/status/', { method: 'POST' }); 
        checkGameStatus(); 
    } catch(e) { console.error(e); }
}

// --- STATUS CHECK ---
async function checkGameStatus() {
    // Αν παίζει το animation των 3 δευτερολέπτων, σταμάτα εδώ
    if (isAnimating) return;

    try {
        const response = await fetch('tavli.php/status/');
        if (!response.ok) throw new Error("Status Fetch Error");
        const status = await response.json();

        currentDice.d1 = status.dice1;
        currentDice.d2 = status.dice2;
        
        if (typeof isHotseat !== 'undefined' && isHotseat === true) {
            myColor = (status.p_turn === 'W') ? 'white' : 'black';
            isMyTurn = true;
        } else {
            isMyTurn = (status.p_turn === 'W' && myColor === 'white') || 
                       (status.p_turn === 'B' && myColor === 'black');
        }

        const btnStart = document.getElementById('btn-start-game');
        const btnRollFirst = document.getElementById('btn-roll-first');
        const btnRoll = document.getElementById('btn-roll');
        const diceDisplay = document.getElementById('dice-display');
        const d1 = document.getElementById('d1'); 
        const d2 = document.getElementById('d2');
        const gameControls = document.getElementById('game-controls'); 
        const turnW = document.getElementById('turn-label-w');
        const turnB = document.getElementById('turn-label-b');

        if(turnW) turnW.style.display = 'none';
        if(turnB) turnB.style.display = 'none';

        // 1. ΑΡΧΙΚΗ
        if (status.status === 'not active') {
            if(btnStart) btnStart.style.display = 'inline-block';
            if(btnRollFirst) btnRollFirst.style.display = 'none';
            if(btnRoll) btnRoll.style.display = 'none';
            if(diceDisplay) diceDisplay.style.display = 'none';
            if(gameControls) gameControls.style.display = 'none';
        } 
        // 2. FIRST ROLL
        else if (status.status === 'first_roll') {
            if(btnStart) btnStart.style.display = 'none';
            if(btnRoll) btnRoll.style.display = 'none';
            if(gameControls) gameControls.style.display = 'block';
            if(btnRollFirst) btnRollFirst.style.display = 'inline-block';
            if(diceDisplay) diceDisplay.style.display = 'block'; 

            // Κείμενο Κουμπιού
            if (status.dice1 === null && status.dice2 === null) {
                btnRollFirst.innerText = "🎲 Ζάρι για " + pWhite; 
                if(d1) d1.innerText = "-";
                if(d2) d2.innerText = "-";
            } 
            else if (status.dice1 !== null && status.dice2 === null) {
                btnRollFirst.innerText = "🎲 Ζάρι για " + pBlack; 
                if(d1) d1.innerText = status.dice1;
                if(d2) d2.innerText = "-";
            } 
            else if (status.dice1 === null && status.dice2 === null) {
                 btnRollFirst.innerText = "Ισοπαλία! Ξανά για " + pWhite;
            }
        } 
        // 3. STARTED
        else {
            if(btnStart) btnStart.style.display = 'none';
            if(btnRollFirst) btnRollFirst.style.display = 'none'; 
            if(gameControls) gameControls.style.display = 'block';

            if (status.p_turn === 'W' && turnW) turnW.style.display = 'block';
            if (status.p_turn === 'B' && turnB) turnB.style.display = 'block';
            
            const hasDice = (status.dice1 !== null || status.dice2 !== null);
            
            if (hasDice) {
                if(diceDisplay) diceDisplay.style.display = 'block';
                if(btnRoll) btnRoll.style.display = 'none';
                if(d1) {
                    d1.innerText = status.dice1 || "-";
                    d1.className = status.dice1 ? 'dice-box' : 'dice-box dice-used';
                }
                if(d2) {
                    d2.innerText = status.dice2 || "-";
                    d2.className = status.dice2 ? 'dice-box' : 'dice-box dice-used';
                }
            } else {
                if(diceDisplay) diceDisplay.style.display = 'none';
                if(btnRoll) btnRoll.style.display = isMyTurn ? 'inline-block' : 'none';
            }
        }
        
        const scoreW = document.getElementById('score-w');
        const scoreB = document.getElementById('score-b');
        if(scoreW) scoreW.innerText = status.score_w || 0;
        if(scoreB) scoreB.innerText = status.score_b || 0;

    } catch (error) { console.error("Status Error:", error); }
}

async function refreshBoard() {
    try {
        const response = await fetch('tavli.php/board/');
        if (!response.ok) throw new Error("Board Fetch Error");
        const data = await response.json();
        boardState = data; 

        for(let i=1; i<=24; i++) {
            const point = document.getElementById('p'+i);
            if(point) {
                point.innerHTML = ''; 
                point.className = 'point'; 
                const newPoint = point.cloneNode(true);
                point.parentNode.replaceChild(newPoint, point);
                newPoint.onclick = () => {
                    if(newPoint.classList.contains('possible-move')) handlePointClick(i);
                };
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
                    
                    if (selectedPieceId === currentPos && i === count - 1) {
                        piece.classList.add('selected-piece');
                        piece.style.border = "3px solid #ff9800";
                        piece.style.boxShadow = "0 0 15px #ffd700";
                        if(isWhite) piece.style.backgroundColor = "#ffd700";
                    }

                    const isMine = (isWhite && myColor === 'white') || (!isWhite && myColor === 'black');
                    if (isMine) {
                        piece.style.cursor = isMyTurn ? 'pointer' : 'not-allowed';
                        piece.onclick = (e) => {
                            e.stopPropagation(); 
                            if (!isMyTurn) return;
                            selectPiece(currentPos); 
                        };
                    } else {
                        piece.style.cursor = 'default';
                        piece.onclick = (e) => e.stopPropagation(); 
                    }
                    triangle.appendChild(piece);
                }
            }
        });
        
        if (selectedPieceId !== null && isMyTurn) showSuggestions(selectedPieceId);
    } catch (error) { console.error(error); }
}

function showSuggestions(startPos) {
    const d1 = parseInt(currentDice.d1) || 0;
    const d2 = parseInt(currentDice.d2) || 0;
    const targets = new Set();
    const selectedSquare = boardState.find(sq => parseInt(sq.x) === startPos);
    const pieceCount = selectedSquare ? parseInt(selectedSquare.piece_count) : 0;
    const isFirstMove = (pieceCount === 15);

    const getTarget = (start, steps) => {
        let t = start - steps; 
        if (t < 1) return -1; 
        const targetSquare = boardState.find(sq => parseInt(sq.x) === t);
        if (targetSquare && parseInt(targetSquare.piece_count) > 0) {
            if (targetSquare.piece_color !== (myColor === 'white' ? 'W' : 'B')) return -1;
        }
        return t;
    };

    if (isFirstMove && d1 > 0 && d2 > 0) {
        let tSum = getTarget(startPos, d1 + d2);
        if (tSum !== -1) targets.add(tSum);
        else if (d1 === 6 && d2 === 6) {
            let tHalf = getTarget(startPos, 6);
            if (tHalf !== -1) targets.add(tHalf);
        }
    } else {
        if(d1 > 0) { let t = getTarget(startPos, d1); if(t!==-1) targets.add(t); }
        if(d2 > 0) { let t = getTarget(startPos, d2); if(t!==-1) targets.add(t); }
        if(d1>0 && d2>0) { let t = getTarget(startPos, d1+d2); if(t!==-1) targets.add(t); }
    }

    targets.forEach(target => {
        const point = document.getElementById('p' + target);
        if(point) point.classList.add('possible-move');
    });
}

function selectPiece(position) {
    const posInt = parseInt(position);
    if (selectedPieceId === posInt) selectedPieceId = null; 
    else selectedPieceId = posInt;
    updateAll(); 
}

async function handlePointClick(targetPosInput) {
    const targetPos = parseInt(targetPosInput);
    if (selectedPieceId === null) return;
    try {
        const response = await fetch('tavli.php/status/', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'move', from: selectedPieceId, to: targetPos, color: myColor })
        });
        const res = await response.json();
        if(res.error) alert(res.error);
        else {
            selectedPieceId = null; 
            await refreshBoard(); 
            setTimeout(async () => {
                if (res.game_over) alert("ΤΕΛΟΣ ΠΑΙΧΝΙΔΙΟΥ");
                await checkGameStatus();
            }, 500); 
        }
    } catch (e) { console.error(e); }
}

async function resetGame() { if(confirm("Reset?")) { await fetch('tavli.php/board/', { method: 'POST' }); updateAll(); } }
async function surrender(color) { if(confirm("Give up?")) { await fetch('tavli.php/status/', { method: 'POST', body: JSON.stringify({ action: 'surrender', color: color }) }); updateAll(); } }

document.addEventListener('DOMContentLoaded', () => { 
    updateAll(); 
    setInterval(updateAll, 3000); 
});