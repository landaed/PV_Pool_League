precision highp float;
varying vec2 vUV;
varying vec3 vPositionW;
varying vec3 posCol;

void main(void) {
    vec3 col = vec3(vUV.y);
    gl_FragColor = vec4(col*vec3(1,0,0),1);//texture2D(textureSampler, vUV);
}
