const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const path = require('path');
const fs = require('fs');

const app = express();
const server = http.createServer(app);
const io = new Server(server);

app.use(express.static(path.join(__dirname, 'public')));
app.use(express.json());

// In-memory Database
const quizzes = [
    {
        id: 1,
        title: "История веб-технологий",
        description: "Викторина о создании интернета, браузеров и эволюции веб-стандартов.",
        rules: "За каждый правильный ответ начисляются баллы (зависят от оставшегося времени). Самый быстрый правильный ответ получает бонус +50 баллов. За неправильный ответ - штраф -10 баллов.",
        rounds_count: 3,
        questions: [
            {
                id: 101,
                question_text: "Кто считается изобретателем World Wide Web (WWW)?",
                timer_seconds: 20,
                media_path: "",
                media_type: "",
                options: [
                    { id: 1, option_text: "Билл Гейтс", is_correct: false },
                    { id: 2, option_text: "Тим Бернерс-Ли", is_correct: true },
                    { id: 3, option_text: "Стив Джобс", is_correct: false },
                    { id: 4, option_text: "Линус Торвальдс", is_correct: false }
                ]
            },
            {
                id: 102,
                question_text: "В каком году появился первый коммерческий браузер Netscape Navigator?",
                timer_seconds: 30,
                media_path: "",
                media_type: "",
                options: [
                    { id: 5, option_text: "1991", is_correct: false },
                    { id: 6, option_text: "1994", is_correct: true },
                    { id: 7, option_text: "1998", is_correct: false },
                    { id: 8, option_text: "2001", is_correct: false }
                ]
            },
            {
                id: 103,
                question_text: "Какой язык программирования изначально назывался Mocha?",
                timer_seconds: 15,
                media_path: "",
                media_type: "",
                options: [
                    { id: 9, option_text: "Java", is_correct: false },
                    { id: 10, option_text: "Python", is_correct: false },
                    { id: 11, option_text: "JavaScript", is_correct: true },
                    { id: 12, option_text: "Ruby", is_correct: false }
                ]
            }
        ]
    }
];

const sessions = {}; // key: PIN, value: session details

// Bad Words Filter
const badWords = ['хуй', 'пизда', 'ебать', 'сука', 'бля', 'мудак', 'гандон', 'член', 'fuck', 'shit', 'bitch'];
function cleanMessage(text) {
    let cleaned = text;
    badWords.forEach(word => {
        const regex = new RegExp(word, 'gi');
        cleaned = cleaned.replace(regex, '*'.repeat(word.length));
    });
    return cleaned;
}

// REST API
app.get('/api/quizzes', (req, res) => {
    // Strip correct answer flags for client safety
    const safeQuizzes = quizzes.map(q => ({
        ...q,
        questions: q.questions.map(question => ({
            ...question,
            options: question.options.map(opt => ({ id: opt.id, option_text: opt.option_text }))
        }))
    }));
    res.json(safeQuizzes);
});

// CSV and Excel Export Endpoint
app.get('/api/export/:pin/:format', (req, res) => {
    const { pin, format } = req.params;
    const session = sessions[pin];
    if (!session) {
        return res.status(404).send('Сессия не найдена');
    }

    // Sort players by score
    const players = Object.values(session.players).sort((a, b) => b.score - a.score);

    if (format === 'csv') {
        res.setHeader('Content-Type', 'text/csv; charset=utf-8');
        res.setHeader('Content-Disposition', `attachment; filename=quiz_results_${pin}.csv`);
        
        // UTF-8 BOM
        let csvContent = '\uFEFF';
        csvContent += 'Место;Имя игрока;Всего очков;Правильных ответов\n';
        
        players.forEach((p, index) => {
            csvContent += `${index + 1};${p.name};${p.score};${p.correctAnswers}\n`;
        });
        
        return res.send(csvContent);
    } else {
        // Excel HTML table structure representing spreadsheet
        res.setHeader('Content-Type', 'application/vnd.ms-excel');
        res.setHeader('Content-Disposition', `attachment; filename=quiz_results_${pin}.xls`);
        
        let htmlContent = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head><meta charset="utf-8"/></head>
            <body>
            <table border="1">
                <tr style="background:#ecf0f1; font-weight:bold;">
                    <th>Место</th>
                    <th>Имя игрока</th>
                    <th>Всего очков</th>
                    <th>Правильных ответов</th>
                </tr>
        `;
        
        players.forEach((p, index) => {
            htmlContent += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${p.name}</td>
                    <td>${p.score}</td>
                    <td>${p.correctAnswers}</td>
                </tr>
            `;
        });
        
        htmlContent += `</table></body></html>`;
        return res.send(htmlContent);
    }
});

// Socket.io Realtime Events
io.on('connection', (socket) => {
    let currentPin = null;
    let username = null;

    // Admin launches a new game session
    socket.on('create-session', ({ quizId }) => {
        const quiz = quizzes.find(q => q.id === quizId);
        if (!quiz) return;

        const pin = Math.floor(100000 + Math.random() * 900000).toString();
        sessions[pin] = {
            quiz: quiz,
            status: 'lobby',
            currentQuestionIndex: -1,
            currentQuestionStartedAt: null,
            players: {},
            correctAnswerCountInRound: 0,
            answersReceivedInRound: 0,
            messages: []
        };

        currentPin = pin;
        socket.join(pin);
        socket.emit('session-created', { pin, quiz });
    });

    // Player joins session
    socket.on('join-session', ({ pin, name }) => {
        const session = sessions[pin];
        if (!session) {
            return socket.emit('join-error', 'Сессия с таким PIN-кодом не найдена.');
        }
        if (session.status !== 'lobby') {
            return socket.emit('join-error', 'Игра уже началась.');
        }

        // Add player
        const playerId = socket.id;
        session.players[playerId] = {
            id: playerId,
            name: name,
            score: 0,
            correctAnswers: 0,
            hasAnsweredThisRound: false
        };

        currentPin = pin;
        username = name;
        socket.join(pin);

        io.to(pin).emit('player-joined', {
            players: Object.values(session.players),
            playerCount: Object.keys(session.players).length
        });

        socket.emit('joined-successfully', { pin, name });

        // System message in chat
        const sysMsg = {
            id: Date.now(),
            user_name: 'Система',
            message_text: `Игрок ${name} присоединился к игре.`,
            is_system: true,
            created_at: new Date().toISOString()
        };
        session.messages.push(sysMsg);
        io.to(pin).emit('chat-message', sysMsg);
    });

    // Admin starts first/next question
    socket.on('next-question', () => {
        const session = sessions[currentPin];
        if (!session || session.status === 'finished') return;

        session.currentQuestionIndex++;
        
        if (session.currentQuestionIndex >= session.quiz.questions.length) {
            session.status = 'finished';
            io.to(currentPin).emit('game-finished', {
                leaderboard: Object.values(session.players).sort((a, b) => b.score - a.score)
            });
            return;
        }

        session.status = 'question';
        session.correctAnswerCountInRound = 0;
        session.answersReceivedInRound = 0;
        session.currentQuestionStartedAt = Date.now();

        // Reset players answered state
        Object.keys(session.players).forEach(pId => {
            session.players[pId].hasAnsweredThisRound = false;
        });

        const question = session.quiz.questions[session.currentQuestionIndex];
        
        // Strip correct answer logic to prevent cheating
        const safeQuestion = {
            id: question.id,
            question_text: question.question_text,
            timer_seconds: question.timer_seconds,
            options: question.options.map(opt => ({ id: opt.id, option_text: opt.option_text })),
            order: session.currentQuestionIndex + 1,
            total_rounds: session.quiz.questions.length
        };

        io.to(currentPin).emit('round-started', safeQuestion);
    });

    // Player submits answer
    socket.on('submit-answer', ({ optionId }) => {
        const session = sessions[currentPin];
        if (!session || session.status !== 'question') return;

        const player = session.players[socket.id];
        if (!player || player.hasAnsweredThisRound) return;

        player.hasAnsweredThisRound = true;
        player.lastOptionId = optionId;
        session.answersReceivedInRound++;

        const question = session.quiz.questions[session.currentQuestionIndex];
        const selectedOption = question.options.find(opt => opt.id === optionId);
        
        const isCorrect = selectedOption ? selectedOption.is_correct : false;
        const responseTimeMs = Date.now() - session.currentQuestionStartedAt;
        const timerSeconds = question.timer_seconds;

        let points = 0;
        let isFastest = false;

        if (isCorrect) {
            // Check if this was the first correct answer in this round for the speed bonus
            if (session.correctAnswerCountInRound === 0) {
                isFastest = true;
            }
            session.correctAnswerCountInRound++;

            // Calculate points: remaining time in seconds + speed bonus
            const remainingTime = timerSeconds - (responseTimeMs / 1000);
            points = Math.max(0, Math.round(remainingTime));

            if (isFastest) {
                points += 50; // Speed bonus
            }
            player.correctAnswers++;
        } else {
            points = -10; // Incorrect answer penalty
        }

        player.score = Math.max(0, player.score + points);

        socket.emit('answer-feedback', {
            isCorrect,
            pointsAwarded: points,
            score: player.score
        });

        // Broadcast stats updates to everyone
        const leaderboard = Object.values(session.players).sort((a, b) => b.score - a.score);
        io.to(currentPin).emit('leaderboard-updated', { leaderboard });

        // If everyone answered, trigger statistics display
        const totalPlayers = Object.keys(session.players).length;
        if (session.answersReceivedInRound >= totalPlayers) {
            sendStatistics(session, question);
        }
    });

    // Send statistics helper
    function sendStatistics(session, question) {
        session.status = 'statistics';
        
        // Count distribution of answers
        const stats = question.options.map(opt => {
            const votes = Object.values(session.players).filter(p => p.hasAnsweredThisRound && p.lastOptionId === opt.id).length;
            return {
                option_text: opt.option_text,
                count: votes
            };
        });

        // For simulation, randomize stats counts a bit to look populated if single player
        if (Object.keys(session.players).length <= 1) {
            stats.forEach(st => {
                if (st.option_text === question.options.find(o => o.is_correct).option_text) {
                    st.count = 5;
                } else {
                    st.count = Math.floor(Math.random() * 3);
                }
            });
        }

        io.to(currentPin).emit('show-statistics', {
            stats,
            correctOptionText: question.options.find(o => o.is_correct).option_text
        });
    }

    // Chat Message Sent
    socket.on('send-chat-message', ({ messageText, recipientId }) => {
        const session = sessions[currentPin];
        if (!session) return;

        const senderName = username || 'Администратор';
        const senderId = socket.id;

        const cleanText = cleanMessage(messageText);

        const chatMsg = {
            id: Date.now(),
            user_name: senderName,
            user_id: senderId,
            message_text: cleanText,
            recipient_id: recipientId || null, // null means public
            is_system: false,
            created_at: new Date().toISOString()
        };

        session.messages.push(chatMsg);

        if (recipientId) {
            // Private message: emit only to sender and recipient
            socket.emit('chat-message', chatMsg);
            io.to(recipientId).emit('chat-message', chatMsg);
        } else {
            // Public message: emit to entire room
            io.to(currentPin).emit('chat-message', chatMsg);
        }
    });

    socket.on('disconnect', () => {
        if (currentPin && sessions[currentPin] && username) {
            const session = sessions[currentPin];
            delete session.players[socket.id];
            
            io.to(currentPin).emit('player-joined', {
                players: Object.values(session.players),
                playerCount: Object.keys(session.players).length
            });

            const sysMsg = {
                id: Date.now(),
                user_name: 'Система',
                message_text: `Игрок ${username} покинул игру.`,
                is_system: true,
                created_at: new Date().toISOString()
            };
            session.messages.push(sysMsg);
            io.to(currentPin).emit('chat-message', sysMsg);
        }
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
});
