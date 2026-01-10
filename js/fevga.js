// fevga.js - FINAL VERSION 

let selectedPieceId = null;
let currentDice = { d1: null, d2: null };
let isMyTurn = false;
let boardState = []; 
let isAnimating = false; 
let currentMovesLeft = 0; 


async function startGame() { 
    try {
        let res = await fetch('tavli.php/status/', { 
            method: 'POST', 
            body: JSON.stringify({ action: 'start' }) 
        }); 
        console.log('res ' + res.status)
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

        // --- 1. Ενημέρωση Ονομάτων (ΜΟΝΟ αν υπάρχουν τα IDs) ---
        
        // ΛΕΥΚΟΣ
        if (dataW.length > 0 && dataW[0].username) {
            const name = dataW[0].username;
            
            // Ενημέρωση Label (Κάτω Αριστερά) - Ψάχνουμε το ID, όχι την κλάση!
            const elW = document.getElementById('p-name-w');
            if(elW) elW.innerText = name; // Αντικαθιστά μόνο το κείμενο μέσα στο span
            
            // Ενημέρωση Scoreboard
            const scW = document.getElementById('score-name-w');
            if(scW) scW.innerText = name;
        }

        // ΜΑΥΡΟΣ
        if (dataB.length > 0 && dataB[0].username) {
            const name = dataB[0].username;
            
            // Ενημέρωση Label (Πάνω Δεξιά)
            const elB = document.getElementById('p-name-b');
            if(elB) elB.innerText = name;
            
            // Ενημέρωση Scoreboard
            const scB = document.getElementById('score-name-b');
            if(scB) scB.innerText = name;
        }


        // --- 2. Λογική Αναμονής (Waiting Overlay) ---
        const overlay = document.getElementById('waiting-overlay');
        
        if (typeof isHotseat !== 'undefined' && isHotseat === false) {
            const player1Ready = (dataW.length > 0 && dataW[0].username);
            const player2Ready = (dataB.length > 0 && dataB[0].username);

            if (player1Ready && player2Ready) {
                if(overlay) overlay.style.display = 'none';
            } else {
                if(overlay) overlay.style.display = 'flex';
            }
        } else {
            if(overlay) overlay.style.display = 'none';
        }

    } catch (e) {
        console.error("Σφάλμα συγχρονισμού παικτών:", e);
    }
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



async function checkGameStatus() {
    if (isAnimating) return;

    try {
        const response = await fetch('tavli.php/status/');
        if (!response.ok) throw new Error("Status Error");
        const status = await response.json();
        
        // Ενημέρωση της global μεταβλητής για τις κινήσεις
        currentMovesLeft = parseInt(status.moves_left); 

        // 1. ΕΛΕΓΧΟΣ ΑΚΥΡΩΣΗΣ (ABORTED / TIMEOUT) - Παραμένει ως έχει
        if (status.status === 'aborted') {
            let myColorCode = (myColor === 'white') ? 'W' : 'B';
            if (status.result === myColorCode) {
                alert("Ο αντίπαλος αποχώρησε (ή έληξε ο χρόνος). Κερδίσατε!");
                window.location.href = 'logout.php'; 
            } else {
                window.location.href = 'index.php'; 
            }
            return;
        }

        // 2. ΑΝΑΓΝΩΡΙΣΗ DEADLOCK - Παραμένει ως έχει
        let dbTurn = status.p_turn;
        let myTurnCode = (myColor === 'white') ? 'W' : 'B';
        if (isMyTurn && dbTurn !== myTurnCode && (currentDice.d1 !== null || currentDice.d2 !== null)) {
            if(status.status === 'started') {
                alert("Δεν υπάρχουν άλλες έγκυρες κινήσεις! Η σειρά περνάει αυτόματα.");
            }
        }

        // 3. ΕΝΗΜΕΡΩΣΗ ΤΟΠΙΚΗΣ ΚΑΤΑΣΤΑΣΗΣ ΖΑΡΙΩΝ
        currentDice.d1 = status.dice1;
        currentDice.d2 = status.dice2;
        
        // 4. ΕΛΕΓΧΟΣ ΣΕΙΡΑΣ (TURN MANAGEMENT)
        if (typeof isHotseat !== 'undefined' && isHotseat === true) {
            // Στο Hotseat, ο παίκτης είναι αυτός που λέει η βάση
            if (status.p_turn === 'W') {
                myColor = 'white';
            } else {
                myColor = 'black';
            }
            isMyTurn = true; // Πάντα true στο Hotseat
        } else {
            // Online mode
            isMyTurn = (status.p_turn === 'W' && myColor === 'white') || 
                       (status.p_turn === 'B' && myColor === 'black');
        }

        // 5. ΔΙΑΧΕΙΡΙΣΗ UI ΣΤΟΙΧΕΙΩΝ
        const btnStart = document.getElementById('btn-start-game');
        const btnRollFirst = document.getElementById('btn-roll-first');
        const btnRoll = document.getElementById('btn-roll');
        const diceDisplay = document.getElementById('dice-display');
        const d1Box = document.getElementById('d1'); 
        const d2Box = document.getElementById('d2');
        const gameControls = document.getElementById('game-controls'); 
        const turnW = document.getElementById('turn-label-w');
        const turnB = document.getElementById('turn-label-b');

        if(turnW) turnW.style.display = 'none';
        if(turnB) turnB.style.display = 'none';

        if (status.status === 'not active') {
            if(btnStart) btnStart.style.display = 'inline-block';
            if(btnRollFirst) btnRollFirst.style.display = 'none';
            if(btnRoll) btnRoll.style.display = 'none';
            if(diceDisplay) diceDisplay.style.display = 'none';
            if(gameControls) gameControls.style.display = 'none';
        } 
        else if (status.status === 'first_roll') {
            if(btnStart) btnStart.style.display = 'none';
            if(btnRoll) btnRoll.style.display = 'none';
            if(gameControls) gameControls.style.display = 'block';
            if(btnRollFirst) btnRollFirst.style.display = 'inline-block';
            if(diceDisplay) diceDisplay.style.display = 'block'; 

            if(d1Box) d1Box.innerText = status.dice1 || "-";
            if(d2Box) d2Box.innerText = status.dice2 || "-";
        } 
        else if (status.status === 'started') {
            if(btnStart) btnStart.style.display = 'none';
            if(btnRollFirst) btnRollFirst.style.display = 'none'; 
            if(gameControls) gameControls.style.display = 'block';

            // Ενημέρωση ετικέτας "Παίζει τώρα" - Καθαρίζουμε πρώτα
            if (turnW) turnW.style.display = 'none';
            if (turnB) turnB.style.display = 'none';
            
            if (status.p_turn === 'W' && turnW) turnW.style.display = 'inline-block';
            if (status.p_turn === 'B' && turnB) turnB.style.display = 'inline-block';
            
            const hasDice = (status.dice1 !== null || status.dice2 !== null);
            const noMoves = (parseInt(status.moves_left) === 0);

            // 1. Εμφάνιση Κουμπιού Roll
            // Αν είναι η σειρά μου ΚΑΙ οι κινήσεις είναι 0, ΠΡΕΠΕΙ να ρίξω
            if (isMyTurn && noMoves) {
                if(btnRoll) btnRoll.style.display = 'inline-block';
                if(diceDisplay) diceDisplay.style.display = 'none'; // ΚΡΥΨΕ τα παλιά ζάρια
            } 
            // Αν υπάρχουν ζάρια και έχω κινήσεις, παίζω (κρύβω το κουμπί)
            else if (isMyTurn && hasDice && !noMoves) {
                if(btnRoll) btnRoll.style.display = 'none';
                if(diceDisplay) diceDisplay.style.display = 'block';
            }
            else {
                if(btnRoll) btnRoll.style.display = 'none';
            }

            // 2. Εμφάνιση Ζαριών
            // Τα δείχνουμε μόνο αν είναι "φρέσκα" (δηλαδή moves_left > 0)
            if (hasDice && !noMoves) {
                if(diceDisplay) diceDisplay.style.display = 'block';
                if(d1Box) {
                    d1Box.innerText = status.dice1;
                    d1Box.className = status.dice1 ? 'dice-box' : 'dice-box dice-used';
                }
                if(d2Box) {
                    d2Box.innerText = status.dice2;
                    d2Box.className = status.dice2 ? 'dice-box' : 'dice-box dice-used';
                }
            } else {
                // ΕΔΩ ΚΡΥΒΟΝΤΑΙ ΤΑ ΖΑΡΙΑ ΤΗΣ ΚΛΗΡΩΣΗΣ
                if(diceDisplay) diceDisplay.style.display = 'none';
            }
        }
        
        // 6. ΕΝΗΜΕΡΩΣΗ ΣΚΟΡ
        const scoreW = document.getElementById('score-w');
        const scoreB = document.getElementById('score-b');
        if(scoreW) scoreW.innerText = status.score_w || 0;
        if(scoreB) scoreB.innerText = status.score_b || 0;

    } catch (error) { 
        console.error("Status Error:", error); 
    }
}


// Ποιος παίζει πρώτος
async function rollFirst() {
    try {
        const response = await fetch('tavli.php/status/', { 
            method: 'POST', 
            body: JSON.stringify({ action: 'roll_first' }) 
        }); 
        const res = await response.json(); 

        // Update Dice Visuals immediately
        const diceDisplay = document.getElementById('dice-display');
        const d1 = document.getElementById('d1');
        const d2 = document.getElementById('d2');
        const btnRollFirst = document.getElementById('btn-roll-first');
        const startMsg = document.getElementById('start-message');
        
        if(diceDisplay) diceDisplay.style.display = 'block';
        if(d1 && res.dice1) d1.innerText = res.dice1;
        if(d2 && res.dice2) d2.innerText = res.dice2;

        // ΕΛΕΓΧΟΣ: Ρίξαμε το δεύτερο ζάρι;
        if (res.dice2 !== null) {
            
            // Περίπτωση Ισοπαλίας
            if (res.dice1 == res.dice2) {
                alert("Ισοπαλία! Ξαναρίξτε.");
                checkGameStatus();
                return;
            }

            // ΕΧΟΥΜΕ ΝΙΚΗΤΗ ΓΙΑ ΤΗΝ ΠΡΩΤΗ ΖΑΡΙΑ
            isAnimating = true;

            // 1. Εξαφάνισε το κουμπί ΑΜΕΣΩΣ
            if(btnRollFirst) btnRollFirst.style.display = 'none';

            // 2. Υπολόγισε το όνομα του νικητή
            let winnerName = "";
            if (parseInt(res.dice1) > parseInt(res.dice2)) {
                winnerName = pWhite; // Το όνομα του Παίκτη 1
            } else {
                winnerName = pBlack; // Το όνομα του Παίκτη 2
            }

            // 3. Εμφάνισε το μήνυμα "Ξεκινάει ο..."
            if(startMsg) {
                startMsg.innerText = "Ξεκινάει ο " + winnerName + "!";
                startMsg.style.display = 'block';
            }

            // 4. Περίμενε 3 δευτερόλεπτα
            setTimeout(() => {
                // Κρύψε το μήνυμα
                if(startMsg) startMsg.style.display = 'none';
                
                // Ξεκλείδωσε και προχώρα στο παιχνίδι
                isAnimating = false;
                checkGameStatus();
            }, 3000); 

        } else {
            // Είναι ακόμα η πρώτη ζαριά, απλά ενημέρωσε το UI
            checkGameStatus(); 
        }

    } catch(e) { 
        console.error(e); 
        isAnimating = false; 
    }
}



async function rollDice() { 
    try {
        await fetch('tavli.php/status/', { method: 'POST' }); 
        checkGameStatus(); 
    } catch(e) { console.error(e); }
}



function showSuggestions(startPos) {
    const d1 = parseInt(currentDice.d1) || 0;
    const d2 = parseInt(currentDice.d2) || 0;
    const targets = new Set();
    
    const getValidTarget = (start, steps) => {
        let t = start - steps;
        if (myColor === 'white') { if (t < 1) return null; }
        else {
            if (start <= 12) { if (t < 1) t += 24; }
            else { if (t < 13) return null; }
        }
        return t;
    };

    const isAvailable = (t) => {
        if (t === null) return false;
        const targetSquare = boardState.find(sq => parseInt(sq.x) === t);
        const myColorCode = (myColor === 'white' ? 'W' : 'B');
        if (targetSquare && parseInt(targetSquare.piece_count) > 0 && targetSquare.piece_color !== myColorCode) return false;
        return true;
    };

    // --- ΛΟΓΙΚΗ ΓΙΑ ΔΙΠΛΕΣ (Εμφάνιση έως 4 βημάτων) ---
    if (d1 > 0 && (d1 === d2 || d2 === 0)) { 
        // Αν έχουμε διπλές, d1 είναι το ζάρι. 
        // Δείχνουμε τόσα πράσινα όσα και το currentMovesLeft
        for (let i = 1; i <= currentMovesLeft; i++) {
            let t = getValidTarget(startPos, i * d1);
            if (isAvailable(t)) {
                targets.add(t);
            } else {
                break; // Αν βρει εμπόδιο, σταματάει να προτείνει πιο μακριά
            }
        }
    } 
    // --- ΛΟΓΙΚΗ ΓΙΑ ΑΠΛΕΣ ---
    else {
        if (d1 > 0) {
            let t1 = getValidTarget(startPos, d1);
            if (isAvailable(t1)) {
                targets.add(t1);
                if (d2 > 0) {
                    let tSum = getValidTarget(startPos, d1 + d2);
                    if (isAvailable(tSum)) targets.add(tSum);
                }
            }
        }
        if (d2 > 0) {
            let t2 = getValidTarget(startPos, d2);
            if (isAvailable(t2)) {
                targets.add(t2);
                if (d1 > 0) {
                    let tSum = getValidTarget(startPos, d1 + d2);
                    if (isAvailable(tSum)) targets.add(tSum);
                }
            }
        }
    }

    targets.forEach(t => {
        const point = document.getElementById('p' + t);
        if (point) point.classList.add('possible-move');
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
        
        if(res.error) {
            alert(res.error);
        } else {
            selectedPieceId = null; 
            // Σημαντικό: Πρώτα ελέγχουμε το status για να αλλάξει το myColor (ειδικά στο Hotseat)
            // και μετά κάνουμε refresh το board.
            await checkGameStatus(); 
            await refreshBoard();
        }
    } catch (e) { console.error(e); }
}

async function resetGame() { 
    if(confirm("Reset?")) { await fetch('tavli.php/board/', { 
        method: 'POST'
    }); 
    updateAll(); 
    } 
}

async function surrender(color) { 
    if(confirm("Give up?")) { await fetch('tavli.php/status/', { 
        method: 'POST', 
        body: JSON.stringify({ action: 'surrender', color: color }) 
    }); 
    updateAll(); 
    } 
}


async function loginPlayer(name, colorCode) {
    if (!name || name === "Waiting...") return; 

    try {
        console.log("Προσπάθεια Login στη βάση για: " + name + " (" + colorCode + ")");
        
        // Καλεί το set_user (PUT tavli.php/players/W ή B)
        await fetch('tavli.php/player/' + colorCode, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username: name })
        });
        
        console.log("Επιτυχία! Ο παίκτης " + name + " γράφτηκε στη βάση.");
    } catch (e) {
        console.error("Σφάλμα κατά το Login του " + colorCode, e);
    }
}


document.addEventListener('DOMContentLoaded', async () => { 
    if (typeof pWhite !== 'undefined' && pWhite) await loginPlayer(pWhite, 'W');
    if (typeof pBlack !== 'undefined' && pBlack) await loginPlayer(pBlack, 'B');

    updateAll(); 

    // Ασφάλεια για τον Server
    if (isHotseat === false) {
        setInterval(updateAll, 6000); 
    }
});