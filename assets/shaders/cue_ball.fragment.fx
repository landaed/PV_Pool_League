precision highp float;

varying vec2 vUV;
varying vec3 vWorldNormal;
uniform vec3 uCol;
uniform vec3 uViewPosition; // Camera position in world space
varying vec3 vWorldPosition;
void main() {
  //  float p = 5.0;
  //  vec3 lightDir = vec3(0, 1.8, 3.14); // World space light direction
    vec3 color = uCol;

    // Diffuse
  //  float diff = max(dot(normalize(vWorldNormal), normalize(lightDir)), 0.0);
  //  diff = floor(diff*p)/p + .3;
    //color *= diff;

    // Specular
//    float specularStrength = 0.01; // Adjust for desired shininess
//    vec3 reflectDir = reflect(-lightDir, normalize(vWorldNormal));
//    float spec = pow(max(dot(lightDir, reflectDir), 0.0), 1.5); // 32.0 is the shininess factor
//    vec3 specular = specularStrength * spec * vec3(1.0, 1.0, 1.0); // White specular highlight

    // Combine diffuse and specular
  //  color += specular;

//    vec3 viewDir = normalize(uViewPosition - vWorldPosition);
//    color *= 1.-smoothstep(.5,.11,dot(viewDir, normalize(vWorldNormal)));
//    color += smoothstep(.4,.39,dot(viewDir, normalize(vWorldNormal)))*vec3(1,1,1);

    gl_FragColor = vec4(color, 1.0);
}
