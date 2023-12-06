attribute vec3 position;
attribute vec3 normal;
attribute vec2 uv;
varying vec2 vUV;
uniform mat4 world;
uniform mat4 worldViewProjection;
uniform vec2 resolution; // Add resolution uniform

void main(void) {
    gl_Position = worldViewProjection * vec4(position, 1.0);
    vUV = uv;
}
