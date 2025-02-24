<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'];

if (!isset($user_id)) {
    echo json_encode(["success" => false, "error" => "Unauthorized access"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['image']) && isset($data['location']) && isset($data['note'])) {
    $image_data = $data['image'];
    $location = $data['location'];
    $note = $data['note'];

    $image_path = 'uploads/' . time() . '.jpg';
    $image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
    $image_data = base64_decode($image_data);

    if (file_put_contents($image_path, $image_data)) {
        $query = "INSERT INTO uploads (user_id, image_path, location, note) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isss", $user_id, $image_path, $location, $note);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "location" => $location, "note" => $note]);
        } else {
            echo json_encode(["success" => false, "error" => "Database error"]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Failed to save image"]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Invalid data"]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/itonatocuescano/CSS/fonts.css">
    <title>Photo Capture</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            font-family: Arial, sans-serif;
        }
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            background: #000;
            z-index: 1000;
        }
        .logo {
            width: 100px;
            height: auto;
        }
        .camera-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #222;
        }
        #brightnessWarning{
            position: absolute;
         color: gold;
          font-size: 20px;
           font-weight: bold;
            top: 50%; 
            left: 50%; 
            z-index: 200;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }
        img {
            width: auto;
            height: auto;
            object-fit: contain;
        }
        canvas { display: none; }
        .bottom-frame {
            position: absolute;
            height: 100px;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(to right, #B8860B, #000);
            padding: 15px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            flex-direction: column;
            
        }
        .buttons {
            display: flex;
            gap: 15px;
        }
        button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
        }
        .capture-btn { background: #ffffff; color: black; }
        .note-btn { background: #ff9800; color: white; }
        .retake-btn { background: rgb(255, 255, 255); color: black; }
        .in-btn { background: rgb(255, 255, 255); color: black; }
        .out-btn { background: rgb(255, 255, 255); color: black; }
        button:hover {
            opacity: 0.8;
        }
        .timer {
            color: white;
            font-size: 20px;
            font-weight: bold;
            margin-top: 10px;
            text-align: center;
        }

        #noteInput {
            display: none;
            margin-top: 10px;
            background: white;
            padding: 10px;
            border-radius: 5px;
            width: 90%;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
            margin-bottom: 40px; /* Adjusted margin-bottom to give more space */
        }

        #saveNote {
            margin-top: 5px; /* Reduced margin-top */
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        #saveNote:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
<div id="brightnessWarning">"Warning: The environment is too dark!" </Div>
<nav class="bg-black text-white fixed top-0 w-full shadow-lg z-50">
    <div class="flex items-center justify-between px-4 py-2 relative">
        <!-- Menu Button (Left) -->
        <div class="relative">
            <button id="menuButton" class="p-2 text-white focus:outline-none">☰</button>
            <div id="dropdownMenu" class="absolute left-0 mt-2 w-48 bg-stone-800 text-black rounded shadow-lg hidden">
                <a href="camera.php" class="block px-4 py-2 text-white hover:bg-stone-700">Camera</a>
                <a href="user_profile.php" class="block px-4 py-2 text-white hover:bg-stone-700">Profile</a>
                <a href="user_gallery.php" class="block px-4 py-2 text-white hover:bg-stone-700">Gallery</a>
                <a href="logout.php" class="block px-4 py-2 text-white hover:bg-stone-700">Logout</a>
            </div>
        </div>

        <!-- Centered Logo -->
        <a href="camera.php" class="absolute left-1/2 transform -translate-x-1/2">
            <img src="images/logo.jpg" alt="Company Logo" class="h-12">
        </a>
    </div>
</nav>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const menuButton = document.getElementById("menuButton");
        const dropdownMenu = document.getElementById("dropdownMenu");

        menuButton.addEventListener("click", () => {
            dropdownMenu.classList.toggle("hidden");
        });

        // Optional: Close the dropdown when clicking outside of it
        document.addEventListener("click", (event) => {
            if (!menuButton.contains(event.target) && !dropdownMenu.contains(event.target)) {
                dropdownMenu.classList.add("hidden");
            }
        });
    });
</script>



    <div class="camera-container">
        <video id="video" autoplay></video>
        <canvas id="canvas"></canvas>
        <img id="photo" style="display:none;">
    </div>

    <div class="bottom-frame">
        <div class="buttons">
            <button id="capture" class="capture-btn">Capture</button>
            <button id="note" class="note-btn">Note</button>
            <button id="retake" class="retake-btn" style="display: none;">Retake</button>
            <button id="in" class="in-btn" style="display: none;">Time In</button>
            <button id="out" class="out-btn" style="display: none;">Time Out</button>
        </div>
        <div id="noteInput">
            <textarea id="noteText" placeholder="Write your note here..." rows="4" class="w-full border-2 border-gray-300 rounded-md p-2"></textarea>
            <button id="saveNote" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded">Save Note</button>
        </div>
        <!-- Timer Display -->
        <div class="timer" id="timerDisplay">Timer: 09:00:00</div>
</div>

    </div>

    <script>
const video = document.getElementById("video");
const canvas = document.getElementById("canvas");
const ctx = canvas.getContext("2d");
const photo = document.getElementById("photo");
const captureBtn = document.getElementById("capture");
const noteBtn = document.getElementById("note");
const retakeBtn = document.getElementById("retake");
const inBtn = document.getElementById("in");
const outBtn = document.getElementById("out");
const saveNoteBtn = document.getElementById("saveNote");
const noteInputDiv = document.getElementById("noteInput");
const noteText = document.getElementById("noteText");
const timerDisplay = document.getElementById("timerDisplay");
const brightnessWarning = document.getElementById("brightnessWarning");

let lastPhotoData = null;
let locationData = "Fetching location...";
let noteData = "";

// Timer variables
let timerStartTime = null;
let timerInterval = null;

// Logo image (update the path as needed)
const logo = new Image();
logo.src = "images/logo.jpg"; // Update to your logo's path

// Access camera
navigator.mediaDevices
    .getUserMedia({ video: { facingMode: "environment" } })
    .then((stream) => (video.srcObject = stream))
    .catch((err) => console.error("Camera access denied:", err));

// Fetch location data
navigator.geolocation.getCurrentPosition(
    async (position) => {
        const { latitude, longitude } = position.coords;
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}`
            );
            const data = await response.json();
            locationData = data.display_name || "Unknown Location";
        } catch (error) {
            locationData = "Location fetch failed";
        }
    },
    () => (locationData = "Location access denied")
);

// Timer initialization
function initializeTimer() {
    const storedStartTime = localStorage.getItem("timerStartTime");
    if (storedStartTime) {
        timerStartTime = parseInt(storedStartTime, 10);
        updateTimer();
    }
}

// Start the timer
function startTimer() {
    timerStartTime = new Date().getTime();
    localStorage.setItem("timerStartTime", timerStartTime.toString());
    updateTimer();
}

// Update the timer
function updateTimer() {
    const now = new Date().getTime();
    const elapsed = now - timerStartTime;

    const remainingTime = Math.max(9 * 60 * 60 * 1000 - elapsed, 0); // 9 hours in milliseconds
    const hours = Math.floor((remainingTime / (1000 * 60 * 60)) % 24);
    const minutes = Math.floor((remainingTime / (1000 * 60)) % 60);
    const seconds = Math.floor((remainingTime / 1000) % 60);

    // Update the timer display
    timerDisplay.innerText = `${hours
        .toString()
        .padStart(2, "0")}:${minutes
        .toString()
        .padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`;

    if (remainingTime > 0) {
        timerInterval = setTimeout(updateTimer, 1000);
    } else {
        clearTimeout(timerInterval);
        alert("Time is up!");
    }
}

// Stop the timer and clear storage
function stopTimer() {
    clearInterval(timerInterval);
    localStorage.removeItem("timerStartTime");
}

// Detect brightness
function detectBrightness() {
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const sampleWidth = 100;
    const sampleHeight = 100;
    const imageData = ctx.getImageData(
        (canvas.width - sampleWidth) / 2,
        (canvas.height - sampleHeight) / 2,
        sampleWidth,
        sampleHeight
    );

    const data = imageData.data;
    let totalBrightness = 0;

    for (let i = 0; i < data.length; i += 4) {
        const r = data[i];
        const g = data[i + 1];
        const b = data[i + 2];
        const brightness = 0.299 * r + 0.587 * g + 0.114 * b;
        totalBrightness += brightness;
    }

    const avgBrightness = totalBrightness / (data.length / 4);

    if (avgBrightness < 50) {
        brightnessWarning.style.display = "block";
        captureBtn.disabled = true;
    } else {
        brightnessWarning.style.display = "none";
        captureBtn.disabled = false;
    }
}

function startBrightnessDetection() {
    setInterval(detectBrightness, 1000);
}

// Capture photo
captureBtn.addEventListener("click", () => {
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    ctx.fillStyle = "black";
    ctx.fillRect(0, 0, canvas.width, 80);

    const logoSize = 100;
    ctx.drawImage(logo, 20, 20, logoSize, logoSize);

    if (noteData) {
        ctx.fillStyle = "white";
        ctx.font = "15px Arial";
        ctx.fillText("Note: " + noteData, 20, canvas.height - 80);
    }

    const dateTime = new Date().toLocaleString();
    ctx.fillText(dateTime, 20, canvas.height - 50);
    ctx.fillText(locationData, 20, canvas.height - 20);

    lastPhotoData = canvas.toDataURL("image/jpeg");
    photo.src = lastPhotoData;
    photo.style.display = "block";
    video.style.display = "none";
    captureBtn.style.display = "none";
    retakeBtn.style.display = "block";
    inBtn.style.display = "block";
    outBtn.style.display = "block";
});

// Upload image
function uploadImage(locationType) {
    if (!lastPhotoData) {
        alert("Please capture an image first!");
        return;
    }

    fetch("upload_image.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            image: lastPhotoData,
            location: locationType,
            note: noteData,
        }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                alert(`Image uploaded successfully to ${locationType}!`);
                retakeBtn.click();
            } else {
                alert("Upload failed: " + data.error);
            }
        })
        .catch(() => alert("Error uploading image."));
}

// Event listeners
retakeBtn.addEventListener("click", () => {
    photo.style.display = "none";
    video.style.display = "block";
    captureBtn.style.display = "inline-block";
    retakeBtn.style.display = "none";
});

inBtn.addEventListener("click", () => {
    uploadImage("In");
    if (!localStorage.getItem("timerStartTime")) {
        startTimer();
    }
});

outBtn.addEventListener("click", () => {
    uploadImage("Out");
    stopTimer();
});

noteBtn.addEventListener("click", () => {
    noteInputDiv.style.display = "block";
    noteText.focus();
});

saveNoteBtn.addEventListener("click", () => {
    noteData = noteText.value.trim();
    noteInputDiv.style.display = "none";
    alert("Note saved!");
});

// Initialize everything on page load
document.addEventListener("DOMContentLoaded", () => {
    initializeTimer();
    startBrightnessDetection();
});

    </script>
</body>
</html>
