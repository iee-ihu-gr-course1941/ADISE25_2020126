// fevga.js - Η Αρχική Σταθερή Έκδοση (Restore)

let selectedPieceId = null;
let currentDice = { d1: null, d2: null };
let isMyTurn = false;
let boardState = []; 

async function updateAll() {
    await checkGameStatus();
    await refreshBoard();
}

// --- BOARD LOGIC ---
async function refreshBoard() {
    try {
        const response = await fetch('tavli.php/board/');
        const data = await response.json();
        boardState = data; 

        // 1. Καθαρισμός
        for(let i=1; i<=24; i++) {
            const point = document.getElementById('p'+i);
            if(point) {
                point.innerHTML = ''; 
                point.className = 'point'; // Reset classes
                
                const newPoint = point.cloneNode(true);
                point.parentNode.replaceChild(newPoint, point);
                
                newPoint.onclick = () => {
                    if(newPoint.classList.contains('possible-move')) {
                        handlePointClick(i);
                    }
                };
            }
        }

        // 2. Τοποθέτηση Πούλιων
        data.forEach(pos => {
            const currentPos = parseInt(pos.x); 
            const count = parseInt(pos.piece_count);
            const triangle = document.getElementById('p' + currentPos);
            
            if(triangle && count > 0) {
                for(let i=0; i<count; i++) {
                    const piece = document.createElement('div');
                    const isWhite = pos.piece_color === 'W';
                    piece.className = 'piece ' + (isWhite ? 'white-piece' : 'black-piece');
                    
                    // --- ΕΛΕΓΧΟΣ ΕΠΙΛΟΓΗΣ (YELLOW HIGHLIGHT) ---
                    // Αν αυτό είναι το πούλι που έχουμε επιλέξει (και είναι το τελευταίο στη στίβα)
                    if (selectedPieceId === currentPos && i === count - 1) {
                        piece.classList.add('selected-piece');
                    }

                    // Click Logic
                    const isMine = (isWhite && myColor === 'white') || (!isWhite && myColor === 'black');
                    
                    if (isMine) {
                        piece.style.cursor = isMyTurn ? 'pointer' : 'not-allowed';
                        
                        piece.onclick = (e) => {
                            e.stopPropagation(); 
                            if (!isMyTurn) return;

                            if (selectedPieceId !== null && selectedPieceId !== currentPos) {
                                // Αν έχω ήδη επιλέξει και πατάω σε άλλο δικό μου πούλι, αλλάζω επιλογή
                                selectPiece(currentPos); 
                            } else {
                                // Επιλογή / Ξε-επιλογή
                                selectPiece(currentPos); 
                            }
                        };
                    } else {
                        piece.style.cursor = 'default';
                        piece.onclick = (e) => e.stopPropagation(); 
                    }
                    
                    triangle.appendChild(piece);
                }
            }
        });
        
        // 3. Εμφάνιση Suggestions (Πράσινα)
        if (selectedPieceId !== null && isMyTurn) {
            showSuggestions(selectedPieceId);
        }

    } catch (error) { console.error(error); }
}

// --- SUGGESTIONS (Διορθωμένο για να πιάνει τα Μαύρα σωστά) ---
function showSuggestions(startPos) {
    const d1 = parseInt(currentDice.d1) || 0;
    const d2 = parseInt(currentDice.d2) || 0;
    const targets = new Set();

    const selectedSquare = boardState.find(sq => parseInt(sq.x) === startPos);
    // Χρησιμοποιούμε parseInt για να είμαστε σίγουροι ότι συγκρίνουμε αριθμούς
    const pieceCount = selectedSquare ? parseInt(selectedSquare.piece_count) : 0;

    // --- ΚΑΝΟΝΑΣ ΠΡΩΤΗΣ ΚΙΝΗΣΗΣ ---
    // Ισχύει αν έχουμε 15 πούλια στη θέση
    const isFirstMove = (pieceCount === 15);

    const getTarget = (start, steps) => {
        // Και οι δύο πάνε αφαιρετικά στο Φεύγα (προς το 1)
        let t = start - steps; 

        // 1. Έλεγχος Ορίων
        if (t < 1) return -1; 

        // 2. Έλεγχος ΑΝΤΙΠΑΛΟΥ
        const targetSquare = boardState.find(sq => parseInt(sq.x) === t);
        if (targetSquare && parseInt(targetSquare.piece_count) > 0) {
            // Αν το χρώμα είναι διαφορετικό
            if (targetSquare.piece_color !== (myColor === 'white' ? 'W' : 'B')) {
                return -1;
            }
        }
        return t;
    };

    // Λογική Υπολογισμού
    if (isFirstMove && d1 > 0 && d2 > 0) {
        // ΠΡΩΤΗ ΚΙΝΗΣΗ: Μόνο το άθροισμα
        let tSum = getTarget(startPos, d1 + d2);
        if (tSum !== -1) targets.add(tSum);
    } else {
        // ΚΑΝΟΝΙΚΗ ΚΙΝΗΣΗ
        if(d1 > 0) {
            let t1 = getTarget(startPos, d1);
            if (t1 !== -1) targets.add(t1);
        }
        if(d2 > 0) {
            let t2 = getTarget(startPos, d2);
            if (t2 !== -1) targets.add(t2);
        }
        if(d1 > 0 && d2 > 0) {
            let tTotal = getTarget(startPos, d1 + d2);
            if (tTotal !== -1) targets.add(tTotal);
        }
    }

    // Ζωγράφισμα Πράσινων
    targets.forEach(target => {
        const point = document.getElementById('p' + target);
        if(point) {
            point.classList.add('possible-move');
        }
    });
}

function selectPiece(position) {
    const posInt = parseInt(position);
    
    // Αν πατήσω στο ίδιο, κάνω deselect
    if (selectedPieceId === posInt) {
        selectedPieceId = null; 
    } else {
        selectedPieceId = posInt;
    }
    // Κάνουμε refresh για να εφαρμοστεί το 'selected-piece' class (κίτρινο)
    updateAll(); 
}

async function handlePointClick(targetPosInput) {
    const targetPos = parseInt(targetPosInput);
    if (selectedPieceId === null) return;
    
    try {
        const response = await fetch('tavli.php/status/', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'move',
                from: selectedPieceId,
                to: targetPos,
                color: myColor
            })
        });
        const res = await response.json();
        
        if(res.error) {
            alert(res.error);
        } else {
            selectedPieceId = null; 
            
            // 1. Δείχνουμε την κίνηση
            await refreshBoard(); 

            // 2. Καθυστέρηση για αίσθηση φυσικής κίνησης
            setTimeout(async () => {
                if (res.game_over) {
                    alert("ΤΕΛΟΣ ΠΑΙΧΝΙΔΙΟΥ (Ολοκληρώθηκαν οι πρώτες κινήσεις)");
                    return; 
                }
                await checkGameStatus();
            }, 500); 
        }
    } catch (e) { console.error(e); }
}

// --- STATUS ---
async function checkGameStatus() {
    try {
        const response = await fetch('tavli.php/status/');
        if (!response.ok) throw new Error(`Server Error: ${response.status}`);
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

        const btnRoll = document.getElementById('btn-roll');
        const diceDisplay = document.getElementById('dice-display');
        const d1 = document.getElementById('d1'); 
        const d2 = document.getElementById('d2');
        const turnW = document.getElementById('turn-label-w');
        const turnB = document.getElementById('turn-label-b');
        const startBtn = document.getElementById('btn-start-game');
        const gameControls = document.getElementById('game-controls'); 

        if(turnW) turnW.style.display = 'none';
        if(turnB) turnB.style.display = 'none';

        if (status.status === 'not active') {
            if(startBtn) startBtn.style.display = 'inline-block';
            if(btnRoll) btnRoll.style.display = 'none';           
            if(diceDisplay) diceDisplay.style.display = 'none';   
            if(gameControls) gameControls.style.display = 'none'; 

        } else {
            if(startBtn) startBtn.style.display = 'none';         
            if(gameControls) gameControls.style.display = 'block';

            const hasDice = (status.dice1 !== null || status.dice2 !== null);
            
            if (hasDice) {
                if(diceDisplay) diceDisplay.style.display = 'block';
                if(btnRoll) btnRoll.style.display = 'none';
                
                d1.innerText = (status.dice1 !== null) ? status.dice1 : "-";
                d1.className = (status.dice1 !== null) ? 'dice-box' : 'dice-box dice-used';

                d2.innerText = (status.dice2 !== null) ? status.dice2 : "-";
                d2.className = (status.dice2 !== null) ? 'dice-box' : 'dice-box dice-used';
            } else {
                if(diceDisplay) diceDisplay.style.display = 'none';
                if(btnRoll) {
                    btnRoll.style.display = isMyTurn ? 'inline-block' : 'none';
                }
            }

            if (status.p_turn === 'W' && turnW) turnW.style.display = 'block';
            if (status.p_turn === 'B' && turnB) turnB.style.display = 'block';
        }
        
        document.getElementById('score-w').innerText = status.score_w || 0;
        document.getElementById('score-b').innerText = status.score_b || 0;

    } catch (error) { console.error("Σφάλμα:", error); }
}

async function startGame() { await fetch('tavli.php/status/', { method: 'POST', body: JSON.stringify({ action: 'start' }) }); updateAll(); }
async function rollDice() { await fetch('tavli.php/status/', { method: 'POST' }); checkGameStatus(); }
async function resetGame() { if(confirm("Reset?")) { await fetch('tavli.php/board/', { method: 'POST' }); updateAll(); } }
async function surrender(color) { if(confirm("Give up?")) { await fetch('tavli.php/status/', { method: 'POST', body: JSON.stringify({ action: 'surrender', color: color }) }); updateAll(); } }

document.addEventListener('DOMContentLoaded', () => { updateAll(); setInterval(updateAll, 3000); });