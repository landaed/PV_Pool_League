precision highp float;

varying vec2 vUV;
varying vec3 vWorldNormal;
uniform vec3 uCol;
uniform vec3 uViewPosition; // Camera position in world space
varying vec3 vWorldPosition;
void main() {
    vec3 col = vec3(1)*smoothstep(.4,.41,length(vUV));
    col = vec3(1);
    gl_FragColor = vec4(uCol, 1.0);
}
