<div x-data="{
    otp: $wire.entangle('{{ $getStatePath() }}'),
    length: 6,
    activeInput: 0,
    handlePaste(e) {
        e.preventDefault();
        const text = e.clipboardData.getData('text');
        const digits = text.split('').filter(c => /\d/.test(c)).slice(0, this.length);
        
        if (digits.length > 0) {
            this.otp = digits.join('');
            this.activeInput = Math.min(digits.length, this.length - 1);
            this.updateBoxes();
        }
    },
    handleInput(e, index) {
        const value = e.target.value;
        // If user typing fast or mobile keyboard behavior
        if (value.length > 1) {
            const digits = value.split('').filter(c => /\d/.test(c)).slice(0, this.length);
            this.otp = digits.join('');
            this.activeInput = Math.min(digits.length, this.length - 1);
            this.updateBoxes();
            return;
        }

        if (value && index < this.length - 1) {
            this.activeInput = index + 1;
        }
        this.updateOtp();
        this.updateBoxes();
    },
    handleKeydown(e, index) {
        if (e.key === 'Backspace' && !this.otp[index] && index > 0) {
            this.activeInput = index - 1;
            this.updateBoxes();
        }
    },
    updateOtp() {
        let val = '';
        for (let i = 0; i < this.length; i++) {
            val += this.$refs['input' + i].value || '';
        }
        this.otp = val;
    },
    updateBoxes() {
        for (let i = 0; i < this.length; i++) {
            this.$refs['input' + i].value = this.otp[i] || '';
        }
        this.$nextTick(() => {
            this.$refs['input' + this.activeInput].focus();
        });
    },
    init() {
        this.updateBoxes();
    }
}" class="flex justify-center gap-2 py-4">
    @for ($i = 0; $i < 6; $i++)
        <input
            x-ref="input{{ $i }}"
            type="text"
            maxlength="1"
            inputmode="numeric"
            @paste="handlePaste($event)"
            @input="handleInput($event, {{ $i }})"
            @keydown="handleKeydown($event, {{ $i }})"
            @focus="activeInput = {{ $i }}"
            class="w-10 h-12 text-center text-xl font-bold border-2 rounded-lg bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 focus:border-primary-600 focus:ring-primary-600 dark:focus:border-primary-500 transition-all duration-200 uppercase"
        />
    @endfor
</div>
