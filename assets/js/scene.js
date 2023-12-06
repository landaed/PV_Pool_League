// Create the Babylon.js engine

var canvas = document.getElementById("renderCanvas");
const engine = new BABYLON.Engine(canvas, true, { preserveDrawingBuffer: true, stencil: true, alpha: true });

// scene.js
// ... your existing Babylon.js setup ...



// ... rest of your Babylon.js code ...

// Create the scene
const scene = new BABYLON.Scene(engine);

//scene.enablePhysics(new BABYLON.Vector3(0, -9.81, 0), new BABYLON.CannonJSPlugin());
scene.enablePhysics(new BABYLON.Vector3(0, 0, 0), new BABYLON.CannonJSPlugin());

scene.clearColor = new BABYLON.Color4(0, 0, 0, 0);

var camera = new BABYLON.ArcRotateCamera("camera1", 0, 0, 5, new BABYLON.Vector3(0, 1, 0), scene);
camera.alpha = 3.14159/2;
camera.beta = 3.14159/2;
//const light2 = new BABYLON.HemisphericLight("HemiLight", new BABYLON.Vector3(0, 1, 0), scene);
//light2.intensity = 1; // Adjusted intensity
var myLogoMesh = null;
var ballImpostors = [];
var offY = 8;
const leftMargin = 4;
var canvasWidth = engine.getRenderWidth();
// Define a point at the left edge of the canvas in screen space
var screenLeft = new BABYLON.Vector3(0, canvas.height / 2, 0);

// Convert the screen space coordinate to world space
var worldLeft = BABYLON.Vector3.Unproject(
    screenLeft,
    canvas.width,
    canvas.height,
    BABYLON.Matrix.Identity(), // World matrix (identity if you're not using a specific world matrix)
    camera.getViewMatrix(),
    camera.getProjectionMatrix()
);

// Now, use the worldLeft x-coordinate to position your objects
// Example for the plane
var offX = worldLeft.x;
var wallx = worldLeft.x*20;
var wally = 10;
if(canvas.width>1390){
   offY = 6;
  offX*=15;
}
console.log(offX);

// Create the Plane facing the camera
var plane = BABYLON.MeshBuilder.CreatePlane("plane", {size: 15}, scene);
plane.position = new BABYLON.Vector3(offX, offY-2, -20); // Adjust position so it's in front of the camera
plane.rotation = new BABYLON.Vector3(Math.PI, 0, 0); // Rotate the plane to face the camera

// Apply a default material (for testing visibility)
// Step 2: Create an Emissive Material
/*var bannerShader = new BABYLON.ShaderMaterial("shader", scene, "./assets/shaders/banner", {
  attributes: ["position", "normal", "uv"],
  uniforms:["world", "worldView", "worldViewProjection", "view", "projection", "resolution", "iTime", "isTap", "tapTime"],
});
bannerShader.backFaceCulling = false; // Optional: Disable backface culling if you want to see both sides of the plane
bannerShader.alphaMode = BABYLON.Engine.ALPHA_COMBINE; // Use ALPHA_COMBINE for standard alpha blending
bannerShader.needDepthPrePass = true; // This helps to correctly sort transparent objects
bannerShader.alpha = 0.8; // Set the overall transparency of the material


*/
var triangleShader = new BABYLON.ShaderMaterial("shader", scene, "./assets/shaders/triangle", {
  attributes: ["position", "normal", "uv"],
  uniforms:["world", "worldView", "worldViewProjection", "view", "projection", "resolution", "iTime", "isTap", "tapTime"],
});
let totalTime = 0;
var firstTap = -1;

// Step 3: Apply the Material to the Plane
plane.material = triangleShader;

var mousePosition = new BABYLON.Vector2();
function getMousePositionInScene(evt) {
    // Get the current pick info for the mouse position
    var pickInfo = scene.pick(scene.pointerX, scene.pointerY);
    if (pickInfo.hit) {
        return pickInfo.pickedPoint;
    } else {
        return null;
    }
}
function getTouchPositionInScene(evt) {
    if (evt.touches.length > 0) {
        var touch = evt.touches[0];
        var canvasRect = canvas.getBoundingClientRect();

        // Calculate normalized touch coordinates
        var normalizedX = (touch.clientX - canvasRect.left) / canvasRect.width;
        var normalizedY = (touch.clientY - canvasRect.top) / canvasRect.height;

        // Convert normalized coordinates to Babylon.js scene coordinates
        var pickInfo = scene.pick(normalizedX * canvas.width, normalizedY * canvas.height);
        if (pickInfo.hit) {
            return pickInfo.pickedPoint;
        }
    }
    return null;
}

window.addEventListener("mousemove", function (evt) {
    var canvasRect = canvas.getBoundingClientRect();

    // Normalize mouse coordinates to 0 to 1 range
    var normalizedX = (evt.clientX - canvasRect.left) / canvasRect.width;
    var normalizedY = (evt.clientY - canvasRect.top) / canvasRect.height;

    mousePosition.x = normalizedX;
    mousePosition.y = 1.0 - normalizedY; // Flip Y axis for GL coordinates
});
window.addEventListener("touchmove", function (evt) {
    var touch = evt.touches[0]; // Get the first touch
    var canvasRect = canvas.getBoundingClientRect();

    // Normalize touch coordinates to 0 to 1 range
    var normalizedX = (touch.clientX - canvasRect.left) / canvasRect.width;
    var normalizedY = (touch.clientY - canvasRect.top) / canvasRect.height;

    mousePosition.x = normalizedX;
    mousePosition.y = 1.0 - normalizedY; // Flip Y axis for GL coordinates
    // Additional code to handle the touch as needed
});

var n = 3;
Promise.all(
  Array.from({ length: n }, (_, x) => {
    return Promise.all(
      Array.from({ length: x + 1 }, (_, y) => {
        return new Promise((resolve) => {

          if(Math.random()>.5){
            BABYLON.SceneLoader.ImportMesh("", "./assets/models/", "cue_ball.glb", scene, function (newMeshes) {
              newMeshes[0].position.x = 0- x+y*2+offX;
              newMeshes[0].position.z = -20;
              newMeshes[0].position.y = -x*2 +offY;
              //newMeshes[2].rotation.x = 1;
              newMeshes[0].name = "cue_ball_" + x + "_" + y;
              // Create physics impostor for each ball
              var sphere1 = BABYLON.MeshBuilder.CreateSphere("sphere", {diameter: 2}, scene);
              sphere1.visibility = 0;

              sphere1.position.x =  newMeshes[0].position.x;
              sphere1.position.z =   newMeshes[0].position.z;
              sphere1.position.y =   newMeshes[0].position.y;

              newMeshes.forEach(mesh => {
                  sphere1.addChild(mesh);
              });
              var randomRotationX = Math.random() * 2 * Math.PI; // Random rotation from 0 to 2π radians for X-axis
              var randomRotationY = Math.random() * 2 * Math.PI; // Random rotation from 0 to 2π radians for Y-axis
              var randomRotationZ = Math.random() * 2 * Math.PI; // Random rotation from 0 to 2π radians for Z-axis
              sphere1.rotation = new BABYLON.Vector3(randomRotationX,randomRotationY, randomRotationZ);
              sphere1.rotation.x = 1;
              var ballImpostor = new BABYLON.PhysicsImpostor(
                  sphere1,
                  BABYLON.PhysicsImpostor.SphereImpostor,
                  { mass: 1, restitution: 0.3,radius: 3},
                  scene
              );
              ballImpostors.push(ballImpostor);

              // Position the ball


              newMeshes.forEach(function (mesh) {
                if (mesh.material) {
                    // Apply new materials based on the name
                    var newMaterial;
                    var cueShader = new BABYLON.ShaderMaterial("shader", scene, "./assets/shaders/cue_ball", {
                      attributes: ["position", "normal", "uv"],
                      uniforms:["world", "worldView", "worldViewProjection", "view", "projection", "uCol"],
                    });
                    switch (mesh.material.name) {


                        case "blue":
                            mesh.material = cueShader;
                            cueShader.setColor3("uCol",new BABYLON.Color3(Math.random(),Math.random(),Math.random())); // Example: Set to a reddish color
                          /*  newMaterial = new BABYLON.StandardMaterial("newMaterialBlue", scene);
                            newMaterial.emissiveColor = new BABYLON.Color3(Math.random(),Math.random(),Math.random());*/
                            break;
                        case "black":
                            mesh.material = cueShader;
                            cueShader.setColor3("uCol",new BABYLON.Color3(1,1,1));
                          /*  newMaterial = new BABYLON.StandardMaterial("newMaterialBlack", scene);
                            newMaterial.emissiveColor = new BABYLON.Color3(1,1,1);*/
                            break;
                        case "white":
                            mesh.material = cueShader;
                            cueShader.setColor3("uCol",new BABYLON.Color3(1,1,1));
                            //newMaterial = new BABYLON.StandardMaterial("newMaterialWhite", scene);
                            //newMaterial.emissiveColor = new BABYLON.Color3(1, 1, 1);
                            break;
                    }

                  /*  if (newMaterial) {
                        mesh.material = cueShader;
                    }*/
                }
                });
              resolve();
            });
          }else{
            BABYLON.SceneLoader.ImportMesh("", "./assets/models/", "cue_ball2.glb", scene, function (newMeshes) {
              newMeshes[0].position.x = 0- x+y*2+offX;
              newMeshes[0].position.z = -20;
              newMeshes[0].position.y = -x*2 +offY;
              //newMeshes[2].rotation.x = 1;
              newMeshes[0].name = "cue_ball_" + x + "_" + y;
              // Create physics impostor for each ball
              var sphere1 = BABYLON.MeshBuilder.CreateSphere("sphere", {diameter: 2}, scene);
              sphere1.visibility = 0;

              sphere1.position.x =  newMeshes[0].position.x;
              sphere1.position.z =   newMeshes[0].position.z;
              sphere1.position.y =   newMeshes[0].position.y;

              newMeshes.forEach(mesh => {
                  sphere1.addChild(mesh);
              });

              var randomRotationX = Math.random() * 2 * Math.PI; // Random rotation from 0 to 2π radians for X-axis
              var randomRotationY = Math.random() * 2 * Math.PI; // Random rotation from 0 to 2π radians for Y-axis
              var randomRotationZ = Math.random() * 2 * Math.PI; // Random rotation from 0 to 2π radians for Z-axis
              sphere1.rotation = new BABYLON.Vector3(randomRotationX,randomRotationY, randomRotationZ);
              sphere1.rotation.x = 1;
              var ballImpostor = new BABYLON.PhysicsImpostor(
                  sphere1,
                  BABYLON.PhysicsImpostor.SphereImpostor,
                  { mass: 1, restitution: 0.3,radius: 3},
                  scene
              );
              ballImpostors.push(ballImpostor);

              // Position the ball


              newMeshes.forEach(function (mesh) {
                if (mesh.material) {

                    // Apply new materials based on the name
                    var newMaterial;
                    var cueShader = new BABYLON.ShaderMaterial("shader", scene, "./assets/shaders/cue_ball", {
                      attributes: ["position", "normal", "uv"],
                      uniforms:["world", "worldView", "worldViewProjection", "view", "projection", "uCol"],
                    });

                    switch (mesh.material.name) {
                        case "blue":
                            mesh.material = cueShader;
                            cueShader.setColor3("uCol",new BABYLON.Color3(Math.random(),Math.random(),Math.random()));
                          //  newMaterial = new BABYLON.StandardMaterial("newMaterialBlue", scene);
                          //  newMaterial.emissiveColor = new BABYLON.Color3(Math.random(),Math.random(),Math.random());
                            break;
                        case "black":
                            mesh.material = cueShader;
                            cueShader.setColor3("uCol",new BABYLON.Color3(Math.random(),Math.random(),Math.random()));

                          //  newMaterial = new BABYLON.StandardMaterial("newMaterialBlack", scene);
                          //  newMaterial.emissiveColor = new BABYLON.Color3(Math.random(),Math.random(),Math.random());
                            break;
                        case "white":
                            mesh.material = cueShader;
                            cueShader.setColor3("uCol",new BABYLON.Color3(1,1,1));

                          //  newMaterial = new BABYLON.StandardMaterial("newMaterialWhite", scene);
                          //  newMaterial.emissiveColor = new BABYLON.Color3(1, 1, 1);
                            break;
                    }

                    if (newMaterial) {
                      //  mesh.material = cueShader;
                    }
                }
                });
              resolve();
            });
          }

        });
      })
    );
  })
).then(() => {
  // Register collision events after all balls are loaded
  ballImpostors.forEach(function(impostor, index) {
    impostor.registerOnPhysicsCollide(ballImpostors, function(mainImpostor, collidedImpostor) {
        console.log("Collision Detected:", mainImpostor.object.name, "collided with", collidedImpostor.object.name);
    });
      document.getElementById('loadingScreen').style.display = 'none';
       document.getElementById('mainContent').style.visibility = 'visible';
});
var newMaterial;
newMaterial = new BABYLON.StandardMaterial("textMat", scene);
newMaterial.emissiveColor = new BABYLON.Color3(1,1,1);

var plane1 = BABYLON.MeshBuilder.CreatePlane("plane", {size: 500}, scene);
plane1.position = new BABYLON.Vector3(0,0, -20.5); // Adjust position so it's in front of the camera
plane1.rotation = new BABYLON.Vector3(Math.PI, 0, 0); // Rotate the plane to face the camera
//plane1.material = newMaterial;

// Step 3: Apply the Material to the Plane
//plane1.material = bannerShader;*/
BABYLON.SceneLoader.ImportMesh("", "./assets/models/", "title_text_1.glb", scene, function (newMeshes) {
  newMeshes[0].position.x = offX;
  newMeshes[0].position.z = -20;
  newMeshes[0].position.y =  offY-6.5;
  newMeshes[0].scaling.x =1.5;
  newMeshes[0].scaling.y =1.5;
  newMeshes[0].rotation = new BABYLON.Vector3(Math.PI, 0, Math.PI);
  //newMeshes[2].rotation.x = 1;

  myLogoMesh = newMeshes[0];
  newMeshes.forEach(function (mesh) {

    var newMaterial;
    newMaterial = new BABYLON.StandardMaterial("textMat", scene);
    newMaterial.emissiveColor = new BABYLON.Color3(1,1,1);
    mesh.material = newMaterial;

    });
});

  // Mouse event listener
  window.addEventListener("mousemove", function (evt) {
    var mousePos3D = getMousePositionInScene(evt);

    if (mousePos3D) {
        ballImpostors.forEach(function (impostor) {
            var ballMesh = impostor.object;

            // Calculate the distance between the mouse position and the ball
            var distance = BABYLON.Vector3.Distance(ballMesh.position, mousePos3D);

            // Check if the mouse is close enough to the ball (e.g., within a radius of 1 unit)
            if (distance < 3) {ballMesh
                if(firstTap < 0){
                  firstTap = totalTime / 1000.0;
                }
                triangleShader.setFloat("tapTime", firstTap);
              //  bannerShader.setFloat("tapTime", firstTap);
                var forceDirection = mousePos3D.subtract(ballMesh.position);
                forceDirection = forceDirection.normalize();
                triangleShader.setFloat("isTap", true);
              //  bannerShader.setFloat("isTap", true);
                // Apply a force to the ball
                impostor.applyImpulse(
                    forceDirection.scale(-1), // Adjust the force magnitude as needed
                    ballMesh.getAbsolutePosition()
                );
            }
        });
    }
});

window.addEventListener("touchmove", function (evt) {
  var mousePos3D = getTouchPositionInScene(evt);

  if (mousePos3D) {
      ballImpostors.forEach(function (impostor) {
          var ballMesh = impostor.object;

          // Calculate the distance between the mouse position and the ball
          var distance = BABYLON.Vector3.Distance(ballMesh.position, mousePos3D);

          // Check if the mouse is close enough to the ball (e.g., within a radius of 1 unit)
          if (distance < 3) {ballMesh
              if(firstTap < 0){
                firstTap = totalTime / 1000.0;
              }
              triangleShader.setFloat("tapTime", firstTap);
              //bannerShader.setFloat("tapTime", firstTap);
              var forceDirection = mousePos3D.subtract(ballMesh.position);
              forceDirection = forceDirection.normalize();
              triangleShader.setFloat("isTap", true);
            //  bannerShader.setFloat("isTap", true);
              // Apply a force to the ball
              impostor.applyImpulse(
                  forceDirection.scale(-1), // Adjust the force magnitude as needed
                  ballMesh.getAbsolutePosition()
              );
          }
      });
  }
});

});

engine.runRenderLoop(function () {
    let deltaTime = engine.getDeltaTime();
    totalTime += deltaTime;

    // Update the iTime uniform
    triangleShader.setFloat("iTime", totalTime / 1000.0); // Convert to seconds
  //  bannerShader.setFloat("iTime", totalTime / 1000.0);
    scene.render();
    triangleShader.setFloat("iTime", scene.getEngine().getDeltaTime() / 1000.0);
    ballImpostors.forEach(function (impostor) {

        var sphere = impostor.object;
        var ballMesh = sphere.getChildren()[0]; // Assuming each sphere has one child, the GLB mesh

        if(sphere.getChildren()[1]){
            ballMesh.addChild(sphere.getChildren()[1]);
        //    ballMesh.rotation = new BABYLON.Vector3(ballMesh.rotation.,ballMesh.rotation.y, 0);
        }
        if(sphere.getChildren()[2]){
            ballMesh.addChild(sphere.getChildren()[2]);
          //  ballMesh.rotation = new BABYLON.Vector3(ballMesh.rotation.x,ballMesh.rotation.y, 0);
        }
        if (ballMesh) {
          //  console.log("got ball mesh!");
            if (impostor) {
              //console.log("got imposter");
                var velocity = impostor.getLinearVelocity();
                if (velocity) {
                    // Calculate rotation based on velocity and sphere radius
                    var angularVelocity = velocity.length() / sphere.getBoundingInfo().boundingSphere.radius;

                    angularVelocity/=60;
                    angularVelocity/=10;
                    // Update rotation (this is a simplified example, you might need to adjust axes)
                    ballMesh.rotation = new BABYLON.Vector3(ballMesh.rotation.x+angularVelocity,ballMesh.rotation.y+ angularVelocity, 0);

                }
            }
        }

        // Lock Z-position of each ball
        if (sphere.position.z !== -20) {
            sphere.position.z = -20;
            // Reset velocity and angular velocity if needed
            impostor.setLinearVelocity(new BABYLON.Vector3(impostor.getLinearVelocity().x, impostor.getLinearVelocity().y, 0));
            impostor.setAngularVelocity(new BABYLON.Vector3(impostor.getAngularVelocity().x, impostor.getAngularVelocity().y, 0));
        }


        if (sphere.position.x >= wallx ) {
          impostor.setLinearVelocity(new BABYLON.Vector3(-5, impostor.getLinearVelocity().y, 0));
          impostor.setAngularVelocity(new BABYLON.Vector3(-impostor.getAngularVelocity().x, impostor.getAngularVelocity().y, 0));
        }
        else if (sphere.position.x <= -wallx) {
          impostor.setLinearVelocity(new BABYLON.Vector3(5, impostor.getLinearVelocity().y, 0));
          impostor.setAngularVelocity(new BABYLON.Vector3(-impostor.getAngularVelocity().x, impostor.getAngularVelocity().y, 0));
        }
        if (sphere.position.y <= -(wally-5)) {
          impostor.setLinearVelocity(new BABYLON.Vector3(impostor.getLinearVelocity().x, 5, 0));
          impostor.setAngularVelocity(new BABYLON.Vector3(impostor.getAngularVelocity().x, -impostor.getAngularVelocity().y, 0));
        }
        if (sphere.position.y >= wally ) {
          impostor.setLinearVelocity(new BABYLON.Vector3(impostor.getLinearVelocity().x, -5, 0));
          impostor.setAngularVelocity(new BABYLON.Vector3(impostor.getAngularVelocity().x, -impostor.getAngularVelocity().y, 0));
        }
    });
});

function formTriangle(ballImpostors, spacing) {
    // Calculate the number of rows in the triangle
//    let n = Math.ceil((-1 + Math.sqrt(1 + 8 * ballImpostors.length)) / 2);

    let index = 0;
    for (let x = 0; x < n; x++) {
        for (let y = 0; y <= x; y++) {
            if (index < ballImpostors.length) {
                if(ballImpostors[index].object){
                  var ballMesh = ballImpostors[index].object;
                  // Set the x and y positions
                  ballMesh.position.x = (y - x / 2) * spacing + offX;
                  ballMesh.position.y = -x * spacing + offY;  // Adjust this line as needed for the y-axis

                  index++;
                }
                else{
                  break;
                }

            }
        }
    }
  //  impostor.setLinearVelocity(new BABYLON.Vector3(0,0, 0));
  //  impostor.setAngularVelocity(new BABYLON.Vector3(0,0, 0));
}

/*
// Example usage
formTriangle(ballImpostors, 1.5, 0, 0);  // Adjust the spacing and offsets as needed


function updateSceneForCanvasSize() {
    var canvasWidth = engine.getRenderWidth();
    var canvasHeight = engine.getRenderHeight();

    // Convert the screen space coordinate to world space
    var worldLeft = BABYLON.Vector3.Unproject(
        new BABYLON.Vector3(0, canvasHeight / 2, 0),
        canvasWidth,
        canvasHeight,
        BABYLON.Matrix.Identity(), // World matrix (identity if you're not using a specific world matrix)
        camera.getViewMatrix(),
        camera.getProjectionMatrix()
    );

    var worldRight = BABYLON.Vector3.Unproject(
        new BABYLON.Vector3(canvasWidth, canvasHeight / 2, 0),
        canvasWidth,
        canvasHeight,
        BABYLON.Matrix.Identity(), // World matrix (identity if you're not using a specific world matrix)
        camera.getViewMatrix(),
        camera.getProjectionMatrix()
    );

    // Calculate new boundaries
    var wallXLeft = worldLeft.x * 15;
    var wallXRight = worldRight.x * 15;
    offX = worldLeft.x;
    var ind=0;
    if(plane.position.x < wallXLeft){
        plane.position.x += 1;
    }
    else{
      plane.position.x -= 1;
    }
    formTriangle(ballImpostors, 1.5);
    ballImpostors.forEach(function(impostor) {
        var ballMesh = impostor.object;

        // Adjust the X position of each ball
        if (ballMesh.position.x < wallXLeft) {
            ballMesh.position.x += 1;
        } else {
            ballMesh.position.x -= 1;
        }
        impostor.setLinearVelocity(new BABYLON.Vector3(0,0, 0));
        impostor.setAngularVelocity(new BABYLON.Vector3(0,0, 0));
    });


}*/

function updateSceneForCanvasSize() {
  canvasWidth = engine.getRenderWidth();
  // Define a point at the left edge of the canvas in screen space
  screenLeft = new BABYLON.Vector3(0, canvas.height / 2, 0);

  // Convert the screen space coordinate to world space
  worldLeft = BABYLON.Vector3.Unproject(
      screenLeft,
      canvas.width,
      canvas.height,
      BABYLON.Matrix.Identity(), // World matrix (identity if you're not using a specific world matrix)
      camera.getViewMatrix(),
      camera.getProjectionMatrix()
  );

  // Now, use the worldLeft x-coordinate to position your objects
  // Example for the plane
  offY = 8;
  offX = worldLeft.x;
  wallx = worldLeft.x*20;

  if(canvasWidth>1390){
    offY = 6;
    offX*=15;
    formTriangle(ballImpostors, 2);
  }
  else{
   formTriangle(ballImpostors, 2);
  }
  console.log(offX);

  // Create the Plane facing the camera
    myLogoMesh.position.x = offX;
    myLogoMesh.position.y = offY -6.5;
  plane.position = new BABYLON.Vector3(offX, offY-2, -20); // Adjust position so it's in front of the camera

}

// Resize the canvas when the window is resized
window.addEventListener("resize", function () {
    updateSceneForCanvasSize();
    engine.resize();
});
