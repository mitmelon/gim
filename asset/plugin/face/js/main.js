try{
  const video = document.getElementById("camera");
  let predictedAges = [];
  let previousEyeOpenness = 0;
  const blinkThreshold = 0.2;
  const videoContainer = document.getElementById("video-container");
  const containerVideo = $('#video-container').html();
  const MODEL_URI = myProPath + 'asset/plugin/face/models'
  $('#loadala').html(theme_loader);
  Promise.all([
    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URI),
    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URI),
    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URI),
    faceapi.nets.faceExpressionNet.loadFromUri(MODEL_URI),
    faceapi.nets.ageGenderNet.loadFromUri(MODEL_URI),
  ])
    .then(playVideo)
    .catch((err) => {
      gToast.error(err);
    });
  
  function playVideo() {
    if (!navigator.mediaDevices) {
      gToast.error("mediaDevices not supported");
      return;
    }
  
    navigator.mediaDevices
      .getUserMedia({
        video: {
          width: { min: 300, ideal: 300, max: 300 },
          height: { min: 320, ideal: 320, max: 320 },
        },
        audio: false,
      })
      .then((stream) => {
        video.srcObject = stream;
      })
      .catch((err) => {
        $('#loadala').remove();
        gToast.error(err);
      });
  
    video.addEventListener("play", () => {
      $('#loadala').remove();
      const canvasSize = { width: video.width, height: video.height };
      faceapi.matchDimensions(canvas, canvasSize);

      
  
      setInterval(async () => {
        const detections = await faceapi
          .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
          .withFaceLandmarks()
          .withFaceExpressions()
          .withAgeAndGender();
  
        if (detections) {
          const DetectionsArray = faceapi.resizeResults(detections, canvasSize);

          canvas
            .getContext("2d")
            .clearRect(0, 0, canvas.width, canvas.height);
  
          faceapi.draw.drawDetections(canvas, DetectionsArray, { withScore: true });
          faceapi.draw.drawFaceLandmarks(canvas, DetectionsArray);
          faceapi.draw.drawFaceExpressions(canvas, DetectionsArray);
  
          if (Object.keys(DetectionsArray).length > 0) {
            let isHuman = DetectionsArray.detection._score;
            let gender = DetectionsArray.gender;
            let age = DetectionsArray.age;
            let interpolatedAge = interpolateAgePredictions(age);
            let expressions = DetectionsArray.expressions;
            let maxValue = Math.max(...Object.values(expressions));
            let emotion = Object.keys(expressions).filter(
              (item) => expressions[item] === maxValue
            );
  
            ageDom.innerHTML = interpolatedAge;
            genderDom.innerHTML = gender;
            // emotionDom.innerHTML = emotion[0];
  
            ageDom.style.backgroundColor = "#12A533";
            genderDom.style.backgroundColor = "#12A533";

            let faceDirection = checkFaceDirection(DetectionsArray);
            let blinkStatus = checkEyeBlinks(DetectionsArray);
  
            gToast.success(faceDirection);
            gToast.success(blinkStatus);
          }
        }
      }, 100);
    });
  }
  
  function interpolateAgePredictions(age) {
    predictedAges = [age].concat(predictedAges).slice(0, 30);
    return predictedAges.reduce((total, a) => total + a) / predictedAges.length;
  }
  
  function checkFaceDirection(faceLandmarks) {
    const leftEye = faceLandmarks.landmarks.getLeftEye();
    const rightEye = faceLandmarks.landmarks.getRightEye();
    const midPointX = (leftEye[0].x + rightEye[3].x) / 2;
    const faceBox = faceLandmarks.detection.box;
  
    return midPointX < faceBox.x + faceBox.width / 2
      ? "Face turned to the left"
      : "Face turned to the right";
  }
  
  function checkEyeBlinks(faceLandmarks) {
    const leftEye = faceLandmarks.landmarks.getLeftEye();
    const rightEye = faceLandmarks.landmarks.getRightEye();
    const leftEyeOpenness = calculateEyeOpenness(leftEye);
    const rightEyeOpenness = calculateEyeOpenness(rightEye);
  
    if (previousEyeOpenness && leftEyeOpenness < blinkThreshold && rightEyeOpenness < blinkThreshold) {
      return "Eye blink detected";
    }
  
    previousEyeOpenness = leftEyeOpenness;
    return "No eye blink detected";
  }
  
  function calculateEyeOpenness(eyeLandmarks) {
    const eyeHeight = eyeLandmarks[3].y - eyeLandmarks[1].y;
    const eyelidDistance = eyeLandmarks[5].y - eyeLandmarks[2].y;
  
    return eyelidDistance / eyeHeight;
  }
}catch (e){
  gToast.error(e)
}