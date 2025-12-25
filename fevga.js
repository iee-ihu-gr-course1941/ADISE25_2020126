// fevga.js

async function updateAll() {
    await checkGameStatus(); // Πρώτα ελέγχουμε το status
    await refreshBoard();    // Μετά ζωγραφίζουμε (ή σβήνουμε) το ταμπλό
}

async function refreshBoard() {
    try {
        const response = await fetch('tavli.php/board/');
        const data = await response.json();
        
        // Καθαρισμός
        for(let i=1; i<=24; i++) {
            const point = document.getElementById('p'+i);
            if(point) point.innerHTML = '';
        }

        // Ζωγραφίζουμε πούλια ΜΟΝΟ αν υπάρχουν (η βάση θα τα στέλνει κενά αν είναι not active)
        data.forEach(pos => {
            const triangle = document.getElementById('p' + pos.x);
            if(triangle && pos.piece_count > 0) {
                for(let i=0; i<pos.piece_count; i++) {
                    const piece = document.createElement('div');
                    piece.className = 'piece ' + (pos.piece_color === 'W' ? 'white-piece' : 'black-piece');
                    triangle.appendChild(piece);
                }
            }
        });
    } catch (error) { console.error(error); }
}

async function checkGameStatus() {
    try {
        const response = await fetch('tavli.php/status/');
        const status = await response.json();

        // Στοιχεία UI
        const startBtn = document.getElementById('btn-start-game');
        const gameControls = document.getElementById('game-controls'); // Το DIV που έχει τα κουμπιά παιχνιδιού
        const diceContainer = document.getElementById('dice-container');
        const scoreW = document.getElementById('score-w');
        const scoreB = document.getElementById('score-b');

        // Ενημέρωση Σκορ
        if(scoreW) scoreW.innerText = status.score_w || 0;
        if(scoreB) scoreB.innerText = status.score_b || 0;

        // --- ΕΛΕΓΧΟΣ ΚΑΤΑΣΤΑΣΗΣ ---
        if (status.status === 'not active') {
            // ΚΑΤΑΣΤΑΣΗ: ΑΝΑΜΟΝΗ
            if(startBtn) startBtn.style.display = 'inline-block'; // Δείξε κουμπί Έναρξης
            if(gameControls) gameControls.style.display = 'none'; // Κρύψε τα άλλα κουμπιά
            if(diceContainer) diceContainer.style.display = 'none'; // Κρύψε ζάρια
        } else {
            // ΚΑΤΑΣΤΑΣΗ: ΠΑΙΧΝΙΔΙ
            if(startBtn) startBtn.style.display = 'none'; // Κρύψε κουμπί Έναρξης
            if(gameControls) gameControls.style.display = 'block'; // Δείξε τα άλλα κουμπιά
            if(diceContainer) diceContainer.style.display = 'block'; // Δείξε ζάρια

            // Λογική εμφάνισης ζαριών (όπως πριν)
            const btnRoll = document.getElementById('btn-roll');
            const diceDisplay = document.getElementById('dice-display');
            const d1 = document.getElementById('d1');
            const d2 = document.getElementById('d2');

            if (status.dice1 && status.dice2) {
                if(btnRoll) btnRoll.style.display = 'none';
                if(diceDisplay) diceDisplay.style.display = 'block';
                if(d1) d1.innerText = status.dice1;
                if(d2) d2.innerText = status.dice2;
            } else {
                if(btnRoll) btnRoll.style.display = 'inline-block';
                if(diceDisplay) diceDisplay.style.display = 'none';
            }
        }

    } catch (error) { console.error(error); }
}

// --- ACTIONS ---

// Κουμπί "Έναρξη Παιχνιδιού"
async function startGame() {
    await fetch('tavli.php/status/', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'start' })
    });
    updateAll();
}

// Κουμπί "Νέο Παιχνίδι (Reset)" -> Πάει στο start screen
async function resetGame() {
    if(confirm("Θέλετε να ακυρώσετε το παιχνίδι και να γυρίσετε στην αρχή;")) {
        await fetch('tavli.php/board/', { method: 'POST' }); // Καλεί το clear_table
        updateAll();
    }
}

async function rollDice() {
    await fetch('tavli.php/status/', { method: 'POST' });
    checkGameStatus();
}

async function surrender(color) {
    if(confirm("Είσαι σίγουρος ότι θες να τα παρατήσεις;")) {
        await fetch('tavli.php/status/', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'surrender', color: color })
        });
        updateAll();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updateAll();
    setInterval(updateAll, 3000); 
});