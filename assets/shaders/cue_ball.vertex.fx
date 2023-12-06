attribute vec3 position;
attribute vec3 normal;
attribute vec2 uv;

varying vec2 vUV;
varying vec3 vWorldNormal; // Transformed normal

uniform mat4 world;
uniform mat4 worldViewProjection;
varying vec3 vWorldPosition;

void main(void) {
    vWorldPosition = (world * vec4(position, 1.0)).xyz;
    gl_Position = worldViewProjection * vec4(position, 1.0);
    vUV = uv;
    vWorldNormal = (world * vec4(normal, 0.0)).xyz; // Transform normal to world space
}
