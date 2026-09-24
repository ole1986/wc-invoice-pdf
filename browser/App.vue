<script setup>
import { ref } from 'vue';
const tab = ref('one')
</script>

<template>
  <v-sheet elevation="2">
    <v-tabs v-model="tab" color="primary">
      <v-tab value="one">General</v-tab>
      <v-tab value="two">Invoice template</v-tab>
      <v-tab value="three">Email template</v-tab>
      <v-tab value="four">Export</v-tab>
    </v-tabs>

    <v-divider></v-divider>

    <v-tabs-window v-model="tab">
      <v-tabs-window-item value="one">
        <v-sheet class="pa-5" color="purple">One</v-sheet>
      </v-tabs-window-item>
      <v-tabs-window-item value="two">
        <v-sheet class="pa-5" color="orange">Two</v-sheet>
      </v-tabs-window-item>
      <v-tabs-window-item value="three">
        <v-sheet class="pa-5" color="brown">Three</v-sheet>
      </v-tabs-window-item>
      <v-tabs-window-item value="four">
        <v-sheet class="pa-5" color="green">Four</v-sheet>
      </v-tabs-window-item>
    </v-tabs-window>
  </v-sheet>
</template>
