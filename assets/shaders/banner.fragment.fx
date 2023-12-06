precision highp float;

// Uniforms
uniform float iTime;
uniform bool isTap;
uniform float tapTime;
varying vec2 vUV;

void main() {
 vec2 uv = vUV; // Use the UV coordinates directly
 vec2 pUV = uv;
 float t = iTime;
 if(isTap){
   t*=smoothstep(1.,1.1,iTime);
   t-=1.1+tapTime;
 }
 else{
   t=-1.;
 }
 vec3 color = vec3(1,.0,.0);
 //t*=.1;
 if(smoothstep(.01-t,.011-t,pUV.x)>.5){
   discard;
 }
 gl_FragColor = vec4(color,.5);
}
