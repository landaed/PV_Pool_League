precision highp float;

// Attributes
attribute vec3 position;
attribute vec2 uv;

// Uniforms
uniform mat4 worldViewProjection;
uniform vec2 mousePosition; // Mouse position in screen space

// Varying
varying vec2 vUV;
varying vec3 posCol;

void main() {
   float threshold = .1; // Threshold for how close the mouse needs to be
   float intensity =.6; // Intensity of the movement towards the mouse
   vUV = uv;
    // Transform position to clip space
    vec4 clipSpacePosition = worldViewProjection * vec4(position, 1.0);

    // Convert to normalized device coordinates (NDC)
    vec3 ndcSpacePosition = clipSpacePosition.xyz / clipSpacePosition.w;

    // Convert NDC to screen space (range [0, 1])
    vec2 screenSpacePosition = ndcSpacePosition.xy * 0.5 + 0.5;

    // Calculate distance from vertex to mouse position in screen space
    float distance = distance(screenSpacePosition, mousePosition);
    if (distance < threshold) {
        // Move vertex towards mouse position
        vec2 moveDirection = mousePosition - screenSpacePosition;
        screenSpacePosition += moveDirection * intensity * (threshold - distance) / threshold;

        // Update clip space position
        ndcSpacePosition.xy = screenSpacePosition * 2.0 - 1.0;
        clipSpacePosition = vec4(ndcSpacePosition * clipSpacePosition.w, clipSpacePosition.w);
    }

    posCol = vec3(1); // Default color
    if (distance < threshold) {
        posCol = vec3(1, 0, 0); // Color change to indicate the effect
    }
  //  vUV = uv;
    gl_Position = clipSpacePosition; // Use updated clip space coordinates for rendering

}
