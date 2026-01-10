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
        if (res.status == 500)
            //...

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

        // 1. ΕΛΕΓΧΟΣ ΑΚΥΡΩΣΗΣ (ABORTED / TIMEOUT)
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

        // 2. ΑΝΑΓΝΩΡΙΣΗ DEADLOCK
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
            // Στο Hotseat, ο παίκτης ακολουθεί τη βάση
            if (status.p_turn === 'W') {
                myColor = 'white';
            } else {
                myColor = 'black';
            }
            isMyTurn = true; 
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

        // Κρύβουμε μόνιμα τα βοηθητικά κουμπιά έναρξης για τώρα
        if(btnStart) btnStart.style.display = 'none';
        if(btnRollFirst) btnRollFirst.style.display = 'none';

        if (status.status === 'not active') {
            if(btnRoll) btnRoll.style.display = 'none';
            if(diceDisplay) diceDisplay.style.display = 'none';
            if(gameControls) gameControls.style.display = 'none';
        } 
        else if (status.status === 'started' || status.status === 'first_roll') {
            // Στο "Started", δείχνουμε τα controls και το ποιος παίζει
            if(gameControls) gameControls.style.display = 'block';

            if(turnW) turnW.style.display = (status.p_turn === 'W') ? 'inline-block' : 'none';
            if(turnB) turnB.style.display = (status.p_turn === 'B') ? 'inline-block' : 'none';
            
            const hasDice = (status.dice1 !== null || status.dice2 !== null);
            const noMoves = (currentMovesLeft === 0);

            // Εμφάνιση Κουμπιού Roll: Μόνο αν είναι η σειρά μου και δεν έχω κινήσεις/ζάρια
            if (isMyTurn && (!hasDice || noMoves)) {
                if(btnRoll) btnRoll.style.display = 'inline-block';
                if(diceDisplay) diceDisplay.style.display = 'none'; 
            } else {
                if(btnRoll) btnRoll.style.display = 'none';
                if(diceDisplay && hasDice) diceDisplay.style.display = 'block';
            }

            // Εμφάνιση Ζαριών
            if (hasDice && !noMoves) {
                if(diceDisplay) diceDisplay.style.display = 'block';
                if(d1Box) {
                    d1Box.innerText = status.dice1 || "-";
                    d1Box.className = status.dice1 ? 'dice-box' : 'dice-box dice-used';
                }
                if(d2Box) {
                    d2Box.innerText = status.dice2 || "-";
                    d2Box.className = status.dice2 ? 'dice-box' : 'dice-box dice-used';
                }
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
    
    // Εύρεση του τετραγώνου που επιλέχθηκε
    const selectedSquare = boardState.find(sq => parseInt(sq.x) === startPos);
    const isMana = (selectedSquare && parseInt(selectedSquare.piece_count) === 15);

    // Βοηθητική συνάρτηση για τον υπολογισμό στόχου με Lap Control
    const getValidTarget = (start, steps) => {
        let t = start - steps;
        if (myColor === 'white') {
            // Άσπρα: 24 -> 1. Αν t < 1, βγήκε εκτός.
            if (t < 1) return null;
        } else {
            // Μαύρα: 12 -> 1 και μετά 24 -> 13.
            if (start <= 12) {
                if (t < 1) t += 24; // Επιτρεπόμενο πέρασμα στο 24
            } else {
                // Αν είναι στο 2ο μισό (24-13) και το t πέσει κάτω από 13, βγήκε εκτός.
                if (t < 13) return null;
            }
        }
        return t;
    };

    // Βοηθητική συνάρτηση για έλεγχο αν η θέση είναι πιασμένη από αντίπαλο
    const isAvailable = (t) => {
        if (t === null) return false;
        const targetSquare = boardState.find(sq => parseInt(sq.x) === t);
        const myColorCode = (myColor === 'white' ? 'W' : 'B');
        if (targetSquare && parseInt(targetSquare.piece_count) > 0 && targetSquare.piece_color !== myColorCode) {
            return false; // Πιασμένο από αντίπαλο
        }
        return true;
    };

    // --- 1. ΛΟΓΙΚΗ ΓΙΑ ΠΡΩΤΗ ΚΙΝΗΣΗ ---
    if (isMana && currentMovesLeft === 1) {
        let steps = (d1 === 6 && d2 === 6) ? 6 : (d1 + d2);
        let t = getValidTarget(startPos, steps);
        if (isAvailable(t)) {
            targets.add(t);
        }
    } 
    // --- 2. ΛΟΓΙΚΗ ΓΙΑ ΔΙΠΛΕΣ (ΚΑΝΟΝΙΚΟ ΠΑΙΧΝΙΔΙ) ---
    else if (d1 > 0 && (d1 === d2 || d2 === 0)) { 
        // d2 === 0 καλύπτει την περίπτωση που έχει ήδη σβήσει το ένα ζάρι οπτικά
        let val = (d1 > 0) ? d1 : d2;
        for (let i = 1; i <= currentMovesLeft; i++) {
            let t = getValidTarget(startPos, i * val);
            if (isAvailable(t)) {
                targets.add(t);
            } else {
                break; // Αν βρει εμπόδιο, δεν μπορεί να πηδήξει πάνω από πιασμένη θέση
            }
        }
    } 
    // --- 3. ΛΟΓΙΚΗ ΓΙΑ ΑΠΛΕΣ ΖΑΡΙΕΣ ---
    else {
        // Έλεγχος για Ζάρι 1
        if (d1 > 0) {
            let t1 = getValidTarget(startPos, d1);
            if (isAvailable(t1)) {
                targets.add(t1);
                // Αν το Ζάρι 1 είναι έγκυρο, ελέγχουμε και το άθροισμα
                if (d2 > 0) {
                    let tSum = getValidTarget(startPos, d1 + d2);
                    if (isAvailable(tSum)) targets.add(tSum);
                }
            }
        }
        // Έλεγχος για Ζάρι 2
        if (d2 > 0) {
            let t2 = getValidTarget(startPos, d2);
            if (isAvailable(t2)) {
                targets.add(t2);
                // Αν το Ζάρι 2 είναι έγκυρο, ελέγχουμε και το άθροισμα
                if (d1 > 0) {
                    let tSum = getValidTarget(startPos, d1 + d2);
                    if (isAvailable(tSum)) targets.add(tSum);
                }
            }
        }
    }

    // Εμφάνιση των πράσινων highlights
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
    //if (typeof pWhite !== 'undefined' && pWhite) await loginPlayer(pWhite, 'W');
    //if (typeof pBlack !== 'undefined' && pBlack) await loginPlayer(pBlack, 'B');

    updateAll(); 

    // Ασφάλεια για τον Server
    if (isHotseat === false) {
        setInterval(updateAll, 6000); 
    }
});