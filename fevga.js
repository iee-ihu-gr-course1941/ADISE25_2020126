// fevga.js

let selectedPieceId = null;
let currentDice = { d1: null, d2: null };
let isMyTurn = false; // Νέα μεταβλητή για να ξέρουμε αν παίζουμε

async function updateAll() {
    await checkGameStatus();
    await refreshBoard();
}

// --- BOARD LOGIC ---
async function refreshBoard() {
    try {
        const response = await fetch('tavli.php/board/');
        const data = await response.json();
        
        // 1. Καθαρισμός Τριγώνων
        for(let i=1; i<=24; i++) {
            const point = document.getElementById('p'+i);
            if(point) {
                point.innerHTML = ''; // Σβήνουμε τα παλιά
                point.className = 'point'; // Καθαρίζουμε όλα τα classes (suggestions κλπ)
                
                // Δημιουργία νέου στοιχείου για να καθαρίσουν τα click events
                const newPoint = point.cloneNode(true);
                point.parentNode.replaceChild(newPoint, point);
                
                // Click στο κενό τρίγωνο (για κίνηση)
                newPoint.onclick = () => {
                   // Κίνηση επιτρέπεται μόνο αν είναι possible-move
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
                    
                    // Έλεγχος: Είναι δικό μου πούλι;
                    const isMine = (isWhite && myColor === 'white') || (!isWhite && myColor === 'black');
                    
                    // --- HIGHLIGHTS (Κίτρινο) ---
                    // Αν είναι επιλεγμένο και είναι το πάνω-πάνω
                    if (selectedPieceId === currentPos && i === count - 1) {
                        piece.classList.add('selected-piece');
                    }

                    // --- CLICKS & CURSOR ---
                    if (isMine) {
                        // Χεράκι ΜΟΝΟ αν είναι η σειρά μου
                        if (isMyTurn) {
                            piece.style.cursor = 'pointer';
                        } else {
                            piece.style.cursor = 'not-allowed'; // Απαγορευτικό αν δεν είναι η σειρά μου
                        }

                        piece.onclick = (e) => {
                            e.stopPropagation(); 
                            // Αν δεν είναι η σειρά μου, δεν κάνω τίποτα
                            if (!isMyTurn) return;

                            if (selectedPieceId !== null && selectedPieceId !== currentPos) {
                                handlePointClick(currentPos); // Move (Stacking)
                            } else {
                                selectPiece(currentPos); // Select
                            }
                        };
                    } else {
                        // Αντίπαλα πούλια
                        piece.style.cursor = 'default';
                        piece.onclick = (e) => e.stopPropagation(); // Απαγόρευση κλικ
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

// --- SUGGESTIONS (ΠΡΑΣΙΝΑ ΚΟΥΤΑΚΙΑ) ---
function showSuggestions(startPos) {
    const d1 = parseInt(currentDice.d1) || 0;
    const d2 = parseInt(currentDice.d2) || 0;
    const targets = new Set(); // Χρησιμοποιούμε Set για να μην έχουμε διπλότυπα

    // Βοηθητική συνάρτηση υπολογισμού στόχου
    const getTarget = (start, steps) => {
        let t;
        
        // Debugging: Δες στην κονσόλα τι χρώμα νομίζει ο browser ότι είσαι
        // console.log("Calculating for:", myColor, "Start:", start, "Steps:", steps);

        if (myColor === 'white') {
            // ΛΕΥΚΑ: Κίνηση προς τα πίσω (π.χ. 24 -> 1)
            t = start - steps; 
        } else {
            // ΜΑΥΡΑ: Κίνηση προς τα εμπρός (π.χ. 1 -> 24)
            t = start + steps; 
        }

        // Έλεγχος Ορίων (Board 1-24)
        // Αν βγει εκτός (π.χ. < 1 ή > 24), επιστρέφουμε -1 (εκτός αν είναι φάση μαζέματος)
        if (t < 1 || t > 24) {
             return -1; 
        }
        
        return t;
    };

    // 1. Μονές κινήσεις
    if(d1 > 0) {
        let t1 = getTarget(startPos, d1);
        if (t1 !== -1) targets.add(t1);
    }
    
    if(d2 > 0) {
        let t2 = getTarget(startPos, d2);
        if (t2 !== -1) targets.add(t2);
    }
    
    // 2. Διπλή κίνηση (Άθροισμα) - Μόνο αν έχουμε και τα δύο ζάρια
    // Σημείωση: Εδώ χρειάζεται προσοχή, πρέπει να είναι ανοιχτό ΚΑΙ το ενδιάμεσο πάτημα.
    // Για απλότητα τώρα το προσθέτουμε απευθείας.
    if(d1 > 0 && d2 > 0) {
        let tTotal = getTarget(startPos, d1 + d2);
        if (tTotal !== -1) targets.add(tTotal);
    }

    // 3. Ζωγραφίζουμε τα πράσινα
    targets.forEach(target => {
        // ΕΛΕΓΧΟΣ: Είναι η θέση άδεια ή έχει δικό μου πούλι ή μόνο ένα αντίπαλο;
        // Αυτό λείπει από τον αρχικό σου κώδικα, αλλά προς το παρόν ας φτιάξουμε τα κουτάκια.
        
        const point = document.getElementById('p' + target);
        if(point) {
            point.classList.add('possible-move');
        }
    });
}

function selectPiece(position) {
    const posInt = parseInt(position);
    if (selectedPieceId === posInt) {
        selectedPieceId = null; // Ξε-επιλογή
    } else {
        selectedPieceId = posInt;
    }
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
            updateAll(); 
        }
    } catch (e) { console.error(e); }
}

// --- STATUS ---
async function checkGameStatus() {
    try {
        const response = await fetch('tavli.php/status/');
        const status = await response.json();

        // Ενημέρωση Global μεταβλητών
        currentDice.d1 = status.dice1;
        currentDice.d2 = status.dice2;
        
        // Έλεγχος: ΕΙΝΑΙ Η ΣΕΙΡΑ ΜΟΥ;
        isMyTurn = (status.p_turn === 'W' && myColor === 'white') || 
                   (status.p_turn === 'B' && myColor === 'black');

        // UI Elements
        const btnRoll = document.getElementById('btn-roll');
        const diceDisplay = document.getElementById('dice-display');
        const d1 = document.getElementById('d1'); 
        const d2 = document.getElementById('d2');
        const turnW = document.getElementById('turn-label-w');
        const turnB = document.getElementById('turn-label-b');
        const startBtn = document.getElementById('btn-start-game');
        const gameControls = document.getElementById('game-controls');

        // Reset
        if(btnRoll) btnRoll.style.display = 'none';
        if(turnW) turnW.style.display = 'none';
        if(turnB) turnB.style.display = 'none';

        if (status.status === 'not active') {
            if(startBtn) startBtn.style.display = 'inline-block';
            if(gameControls) gameControls.style.display = 'none';
            if(diceDisplay) diceDisplay.style.display = 'none';
        } else {
            if(startBtn) startBtn.style.display = 'none';
            if(gameControls) gameControls.style.display = 'block';

            // Ζάρια Logic
            const hasDice = (status.dice1 !== null || status.dice2 !== null);
            
            if (hasDice) {
                if(diceDisplay) diceDisplay.style.display = 'block';
                
                if (status.dice1 !== null) {
                    d1.innerText = status.dice1;
                    d1.classList.remove('dice-used');
                } else {
                    d1.innerText = "-";
                    d1.classList.add('dice-used');
                }

                if (status.dice2 !== null) {
                    d2.innerText = status.dice2;
                    d2.classList.remove('dice-used');
                } else {
                    d2.innerText = "-";
                    d2.classList.add('dice-used');
                }
            } else {
                if(diceDisplay) diceDisplay.style.display = 'none';
                
                // Κουμπί Roll: Μόνο αν είναι η σειρά μου
                if(isMyTurn && btnRoll) {
                    btnRoll.style.display = 'inline-block';
                }
            }

            // Turn Labels (Δείχνει τίνος σειρά είναι)
            if (status.p_turn === 'W' && turnW) turnW.style.display = 'block';
            if (status.p_turn === 'B' && turnB) turnB.style.display = 'block';
        }
        
        document.getElementById('score-w').innerText = status.score_w || 0;
        document.getElementById('score-b').innerText = status.score_b || 0;

    } catch (error) { console.error(error); }
}

async function startGame() { await fetch('tavli.php/status/', { method: 'POST', body: JSON.stringify({ action: 'start' }) }); updateAll(); }
async function rollDice() { await fetch('tavli.php/status/', { method: 'POST' }); checkGameStatus(); }
async function resetGame() { if(confirm("Reset?")) { await fetch('tavli.php/board/', { method: 'POST' }); updateAll(); } }
async function surrender(color) { if(confirm("Give up?")) { await fetch('tavli.php/status/', { method: 'POST', body: JSON.stringify({ action: 'surrender', color: color }) }); updateAll(); } }

document.addEventListener('DOMContentLoaded', () => { updateAll(); setInterval(updateAll, 3000); });